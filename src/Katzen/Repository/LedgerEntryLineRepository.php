<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\LedgerEntryLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LedgerEntryLine>
 */
class LedgerEntryLineRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, LedgerEntryLine::class);
  }
}
