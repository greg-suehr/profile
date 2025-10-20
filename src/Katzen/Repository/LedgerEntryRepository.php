<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\LedgerEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LedgerEntry>
 */
class LedgerEntryRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, LedgerEntry::class);
  }

  public function sumBalance(Account $account, ?\DateTimeInterface $asOf = null): float
  {
    $qb = $this->createQueryBuilder('l')
        ->select('COALESCE(SUM(CAST(l.debit AS decimal) - CAST(l.credit AS decimal)), 0)')
        ->innerJoin('l.account', 'a')
        ->where('a.id = :accountId')
        ->setParameter('accountId', $account->getId());
    
    if ($asOf) {
      $qb->andWhere('l.entry.timestamp <= :asOf')
            ->setParameter('asOf', $asOf);
    }
    
    return (float) $qb->getQuery()->getSingleScalarResult();
  }
  
  public function trialBalance(\DateTimeInterface $date): array
  {
    return $this->createQueryBuilder('l')
        ->select('a.code, a.name, 
                 COALESCE(SUM(CAST(l.debit AS decimal)), 0) as total_debit,
                 COALESCE(SUM(CAST(l.credit AS decimal)), 0) as total_credit')
        ->innerJoin('l.account', 'a')
        ->where('l.entry.timestamp <= :date')
        ->setParameter('date', $date)
        ->groupBy('a.id')
        ->orderBy('a.code', 'ASC')
        ->getQuery()
        ->getResult();
  }

  public function findByFilters(array $filters): array
  {
    $qb = $this->createQueryBuilder('l');
    
    if (isset($filters['account_id'])) {
      $qb->where('l.account.id = :accountId')
            ->setParameter('accountId', $filters['account_id']);
    }
    
    if (isset($filters['from'])) {
      $qb->andWhere('l.entry.timestamp >= :from')
            ->setParameter('from', $filters['from']);
    }
    
    if (isset($filters['to'])) {
      $qb->andWhere('l.entry.timestamp <= :to')
            ->setParameter('to', $filters['to']);
    }
    
    return $qb->orderBy('l.entry.timestamp', 'DESC')
        ->getQuery()
        ->getResult();
  }
}
