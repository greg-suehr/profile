<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockTransaction>
 */
class StockTransactionRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockTransaction::class);
  }

  public function save(StockTransaction $txn): void
  {
    $this->getEntityManager()->persist($txn);
    $this->getEntityManager()->flush();
  }

  public function sumConsumedSince(StockTarget $target, \DateTimeImmutable $since): ?float
  {
    return (float) $this->createQueryBuilder('t')
        ->select('ABS(SUM(t.qty))')
        ->andWhere('t.stockTarget = :st')
        ->andWhere('t.useType = :ut')
        ->andWhere('t.createdAt >= :since')
        ->setParameter('st', $target)
        ->setParameter('ut', 'consumption')
        ->setParameter('since', $since)
        ->getQuery()
        ->getSingleScalarResult();
  }
}
