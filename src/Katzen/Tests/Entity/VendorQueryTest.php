<?php

namespace App\Katzen\Tests\Entity;

use App\Katzen\Entity\Vendor;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VendorQueryTest extends KernelTestCase
{

  private ?EntityManager $entityManager;
  
  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    
    $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
  }

  public function testSearchByName(): void
  {
    $vendor = $this->entityManager
      ->getRepository(Vendor::class)
      ->findOneBy(['name' => 'Premium Coffee Roasters'])
     ;
    
    $this->assertSame('800-555-0100', $vendor->getPhone());
  }

  protected function tearDown(): void
  {
    parent::tearDown();

    $this->entityManager->close();
    $this->entityManager = null;
  }
}
