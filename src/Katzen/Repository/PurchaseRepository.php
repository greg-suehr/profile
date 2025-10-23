<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 */
class PurchaseRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Purchase::class);
  }

   public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function add(Purchase $purchase): void
  {
    $this->getEntityManager()->persist($purchase);
  }

  public function save(Purchase $purchase): void
  {
    $this->getEntityManager()->persist($purchase);
    $this->getEntityManager()->flush();
  }
  
}
