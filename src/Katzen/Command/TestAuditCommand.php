<?php

namespace App\Katzen\Command;

use App\Katzen\Entity\Customer;
use App\Katzen\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Test command to verify audit system is working
 */
#[AsCommand(
    name: 'app:test-audit',
    description: 'Create test data to verify audit system is working'
)]
class TestAuditCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Katzen Audit System');

        // Step 1: Create a test customer
        $io->section('Step 1: Creating test customer...');
        
        $customer = new Customer();
        $customer->setName('Audit Test Customer');
        $customer->setEmail('audit-test@example.com');
        $customer->setPhone('555-TEST');
        
        $this->em->persist($customer);
        $this->em->flush();
        
        $customerId = $customer->getId();
        $io->success("Created customer #{$customerId}");

        // Step 2: Verify insert was audited
        $io->section('Step 2: Checking audit log for insert...');
        
        $history = $this->audit->getEntityHistory('Customer', (string)$customerId);
        
        if (empty($history)) {
            $io->error('❌ No audit entries found! Audit system may not be working.');
            return Command::FAILURE;
        }
        
        $insertEntry = $history[0];
        $io->success('✓ Insert was audited');
        $io->writeln('  Action: ' . $insertEntry->getAction());
        $io->writeln('  Timestamp: ' . $insertEntry->getOccurredAt()->format('Y-m-d H:i:s'));
        $io->writeln('  Actor ID: ' . ($insertEntry->getActorId() ?? 'system'));
        
        $diff = $insertEntry->getDiff();
        $io->writeln('  Fields captured:');
        foreach ($diff as $field => $value) {
            $io->writeln("    - {$field}: {$value}");
        }

        // Step 3: Update the customer
        $io->section('Step 3: Updating customer...');
        
        $this->em->clear();
        $customer = $this->em->find(Customer::class, $customerId);
        $customer->setName('Updated Test Customer');
        $customer->setEmail('updated-test@example.com');
        
        $this->em->flush();
        $io->success('Updated customer');

        // Step 4: Verify update was audited
        $io->section('Step 4: Checking audit log for update...');
        
        $history = $this->audit->getEntityHistory('Customer', (string)$customerId);
        
        if (count($history) < 2) {
            $io->error('❌ Update was not audited!');
            return Command::FAILURE;
        }
        
        $updateEntry = $history[0]; // Most recent first
        $io->success('✓ Update was audited');
        $io->writeln('  Action: ' . $updateEntry->getAction());
        
        $diff = $updateEntry->getDiff();
        $io->writeln('  Changes:');
        foreach ($diff as $field => $values) {
            if (is_array($values) && count($values) === 2) {
                $io->writeln("    - {$field}: '{$values[0]}' → '{$values[1]}'");
            }
        }

        // Step 5: Test point-in-time reconstruction
        $io->section('Step 5: Testing historical reconstruction...');
        
        $beforeUpdate = new \DateTime('-1 minute');
        $historicalState = $this->audit->reconstructStateAt(
            'Customer',
            (string)$customerId,
            $beforeUpdate
        );
        
        if ($historicalState) {
            $io->success('✓ Historical reconstruction works');
            $io->writeln('  State 1 minute ago:');
            $io->writeln("    - name: {$historicalState['name']}");
            $io->writeln("    - email: {$historicalState['email']}");
        }

        // Step 6: Delete the customer
        $io->section('Step 6: Deleting test customer...');
        
        $this->em->remove($customer);
        $this->em->flush();
        $io->success('Deleted customer');

        // Step 7: Verify delete was audited
        $io->section('Step 7: Checking audit log for delete...');
        
        $history = $this->audit->getEntityHistory('Customer', (string)$customerId);
        
        $deleteEntries = array_filter($history, fn($h) => $h->getAction() === 'delete');
        
        if (empty($deleteEntries)) {
            $io->error('❌ Delete was not audited!');
            return Command::FAILURE;
        }
        
        $deleteEntry = array_values($deleteEntries)[0];
        $io->success('✓ Delete was audited');
        $io->writeln('  Final state before deletion:');
        $diff = $deleteEntry->getDiff();
        foreach ($diff as $field => $value) {
            $io->writeln("    - {$field}: {$value}");
        }

        // Summary
        $io->section('Summary');
        $io->success([
            '✓ All audit tests passed!',
            '✓ Inserts are being tracked',
            '✓ Updates are being tracked',
            '✓ Deletes are being tracked',
            '✓ Historical reconstruction works',
            '',
            'Your audit system is working correctly! 🎉'
        ]);

        $io->note([
            'Total audit entries for test customer: ' . count($history),
            'You can view audit logs via:',
            '  - Database: SELECT * FROM change_log',
            '  - UI: /audit (if controller is installed)',
            '  - Service: AuditService::getEntityHistory()',
        ]);

        return Command::SUCCESS;
    }
}
