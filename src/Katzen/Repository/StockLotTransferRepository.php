<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLotTransfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLotTransfer>
 */
class StockLotTransferRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLotTransfer::class);
  }
}
