<?php

namespace App\Katzen\Tests\Audit;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Order;
use App\Katzen\Repository\ChangeLogRepository;
use App\Katzen\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the Audit/ChangeLog system
 */
class AuditServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ChangeLogRepository $changeLogRepo;
    private AuditService $auditService;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->changeLogRepo = static::getContainer()->get(ChangeLogRepository::class);
        $this->auditService = static::getContainer()->get(AuditService::class);
        
        // Clear any existing audit logs for clean tests
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE change_log');
    }

    public function testEntityInsertIsAudited(): void
    {
        // Create a new customer
        $customer = new Customer();
        $customer->setName('Test Customer');
        $customer->setEmail('test@example.com');
        $customer->setPhone('555-1234');
        
        $this->em->persist($customer);
        $this->em->flush();
        
        // Verify audit log was created
        $history = $this->auditService->getEntityHistory('Customer', (string)$customer->getId());
        
        $this->assertCount(1, $history, 'Should have exactly one audit entry for insert');
        $this->assertEquals('insert', $history[0]->getAction());
        
        $diff = $history[0]->getDiff();
        $this->assertEquals('Test Customer', $diff['name']);
        $this->assertEquals('test@example.com', $diff['email']);
    }

    public function testEntityUpdateIsAudited(): void
    {
        // Create and persist a customer
        $customer = new Customer();
        $customer->setName('Original Name');
        $customer->setEmail('original@example.com');
        
        $this->em->persist($customer);
        $this->em->flush();
        $this->em->clear();
        
        // Update the customer
        $customer = $this->em->find(Customer::class, $customer->getId());
        $customer->setName('Updated Name');
        $customer->setEmail('updated@example.com');
        
        $this->em->flush();
        
        // Verify update was audited
        $history = $this->auditService->getEntityHistory('Customer', (string)$customer->getId());
        
        $this->assertCount(2, $history, 'Should have insert + update entries');
        
        $updateEntry = $history[0]; // Most recent first
        $this->assertEquals('update', $updateEntry->getAction());
        
        $diff = $updateEntry->getDiff();
        $this->assertEquals(['Original Name', 'Updated Name'], $diff['name']);
        $this->assertEquals(['original@example.com', 'updated@example.com'], $diff['email']);
    }

    public function testEntityDeleteIsAudited(): void
    {
        // Create a customer
        $customer = new Customer();
        $customer->setName('To Be Deleted');
        $customer->setEmail('delete@example.com');
        
        $this->em->persist($customer);
        $this->em->flush();
        
        $customerId = $customer->getId();
        
        // Delete the customer
        $this->em->remove($customer);
        $this->em->flush();
        
        // Verify delete was audited
        $history = $this->auditService->getEntityHistory('Customer', (string)$customerId);
        
        $deleteEntry = array_filter($history, fn($h) => $h->getAction() === 'delete');
        $this->assertNotEmpty($deleteEntry, 'Should have a delete entry');
        
        $deleteEntry = array_values($deleteEntry)[0];
        $diff = $deleteEntry->getDiff();
        $this->assertEquals('To Be Deleted', $diff['name']);
    }

    public function testBulkOperationsShareRequestId(): void
    {
        // Create multiple customers in one flush
        $customer1 = new Customer();
        $customer1->setName('Bulk Customer 1');
        $customer1->setEmail('bulk1@example.com');
        
        $customer2 = new Customer();
        $customer2->setName('Bulk Customer 2');
        $customer2->setEmail('bulk2@example.com');
        
        $this->em->persist($customer1);
        $this->em->persist($customer2);
        $this->em->flush();
        
        // Get audit entries
        $history1 = $this->auditService->getEntityHistory('Customer', (string)$customer1->getId());
        $history2 = $this->auditService->getEntityHistory('Customer', (string)$customer2->getId());
        
        $requestId1 = $history1[0]->getRequestId();
        $requestId2 = $history2[0]->getRequestId();
        
        $this->assertEquals($requestId1, $requestId2, 'Bulk operations should share request ID');
        
        // Test getting all changes for this request
        $requestChanges = $this->auditService->getRequestChanges($requestId1);
        $this->assertGreaterThanOrEqual(2, count($requestChanges), 'Should include both customer inserts');
    }

    public function testFieldHistoryTracking(): void
    {
        // Create customer
        $customer = new Customer();
        $customer->setName('Original');
        $customer->setEmail('test@example.com');
        
        $this->em->persist($customer);
        $this->em->flush();
        $this->em->clear();
        
        // Update name multiple times
        $customer = $this->em->find(Customer::class, $customer->getId());
        $customer->setName('First Update');
        $this->em->flush();
        $this->em->clear();
        
        $customer = $this->em->find(Customer::class, $customer->getId());
        $customer->setName('Second Update');
        $this->em->flush();
        
        // Get field-specific history
        $nameHistory = $this->auditService->getFieldHistory(
            'Customer',
            (string)$customer->getId(),
            'name'
        );
        
        $this->assertGreaterThanOrEqual(3, count($nameHistory), 
            'Should track insert + 2 updates for name field');
    }

    public function testReconstructHistoricalState(): void
    {
        // Create customer with initial state
        $customer = new Customer();
        $customer->setName('Version 1');
        $customer->setEmail('v1@example.com');
        
        $this->em->persist($customer);
        $this->em->flush();
        
        $timestamp1 = new \DateTime();
        sleep(1); // Ensure different timestamps
        
        // Update to version 2
        $customer->setName('Version 2');
        $customer->setEmail('v2@example.com');
        $this->em->flush();
        
        $timestamp2 = new \DateTime();
        sleep(1);
        
        // Update to version 3
        $customer->setName('Version 3');
        $customer->setEmail('v3@example.com');
        $this->em->flush();
        
        // Reconstruct state at different points in time
        $stateAtV1 = $this->auditService->reconstructStateAt(
            'Customer',
            (string)$customer->getId(),
            $timestamp1
        );
        
        $stateAtV2 = $this->auditService->reconstructStateAt(
            'Customer',
            (string)$customer->getId(),
            $timestamp2
        );
        
        $this->assertEquals('Version 1', $stateAtV1['name']);
        $this->assertEquals('v1@example.com', $stateAtV1['email']);
        
        $this->assertEquals('Version 2', $stateAtV2['name']);
        $this->assertEquals('v2@example.com', $stateAtV2['email']);
    }

    public function testSensitiveFieldsNotAudited(): void
    {
        // This test assumes you've added a password field to an entity
        // and configured it in AuditConfig::GLOBAL_BLACKLIST
        
        // For now, just verify the config is working
        $config = static::getContainer()->get(\App\Katzen\Service\Audit\AuditConfig::class);
        
        $this->assertFalse(
            $config->shouldAuditField('User', 'password'),
            'Password field should never be audited'
        );
        
        $this->assertFalse(
            $config->shouldAuditField('User', 'api_key'),
            'API key field should never be audited'
        );
    }

    public function testActivitySummary(): void
    {
        // Create multiple entities
        $customer1 = new Customer();
        $customer1->setName('Customer A');
        $customer1->setEmail('a@example.com');
        
        $customer2 = new Customer();
        $customer2->setName('Customer B');
        $customer2->setEmail('b@example.com');
        
        $this->em->persist($customer1);
        $this->em->persist($customer2);
        $this->em->flush();
        
        // Update one
        $customer1->setName('Customer A Updated');
        $this->em->flush();
        
        // Get activity summary
        $since = new \DateTime('-1 hour');
        $summary = $this->auditService->getActivitySummary($since);
        
        $this->assertNotEmpty($summary, 'Should have activity summary');
        
        // Find Customer entries
        $customerActivity = array_filter($summary, fn($s) => $s['entity_type'] === 'Customer');
        $this->assertNotEmpty($customerActivity, 'Should have Customer activity');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up
        $this->em->close();
    }
}
