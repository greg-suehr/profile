<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vendor>
 */
class VendorRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Vendor::class);
  }

  public function save(Vendor $vendor): void
  {
    $this->getEntityManager()->persist($vendor);
    $this->getEntityManager()->flush();
  }
}
