<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockReceipt>
 */
class StockReceiptRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockReceipt::class);
  }

  public function save(StockReceipt $entity, bool $flush = true): void
  {
    $this->getEntityManager()->persist($entity);
    
    if ($flush) {
        $this->getEntityManager()->flush();
    }
  }
  
  public function remove(StockReceipt $entity, bool $flush = true): void
  {
    $this->getEntityManager()->remove($entity);

    if ($flush) {
        $this->getEntityManager()->flush();
     }
  }

  /**
   * Find receipts by purchase order
   */
  public function findByPurchase(int $purchaseId): array
  {
    return $this->createQueryBuilder('r')
            ->andWhere('r.purchase = :purchase')
            ->setParameter('purchase', $purchaseId)
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find receipts by date range
   */
  public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
  {
    return $this->createQueryBuilder('r')
            ->andWhere('r.received_at >= :from')
            ->andWhere('r.received_at <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.received_at', 'DESC')
            ->getQuery()
            ->getResult();
  }
}
