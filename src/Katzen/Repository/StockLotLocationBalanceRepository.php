<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLotLocationBalance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLotLocationBalance>
 */
class StockLotLocationBalanceRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLotLocationBalance::class);
  }
}
