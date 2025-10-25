<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLocation>
 */
class StockLocationRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLocation::class);
  }
}
