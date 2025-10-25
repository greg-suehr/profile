<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLotAllocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLotAllocation>
 */
class StockLotAllocationRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLotAllocation::class);
  }
}
