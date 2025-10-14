<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Order::class);
  }

  public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function save(Order $order): void
  {
    $this->getEntityManager()->persist($order);
    $this->getEntityManager()->flush();
  }

  public function findByDateRange(\DateTimeInterface$from, \DateTimeInterface $to): array
  {
    return $this->createQueryBuilder('o')
                 ->andWhere('o.created_at >= :from')
                 ->setParameter('from', $from)
                 ->andWhere('o.created_at <= :to')
                 ->setParameter('to', $to)      
                 ->orderBy('o.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findByStatus($value): array
  {
    return $this->createQueryBuilder('o')
                 ->andWhere('o.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('o.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findOpen(): array
  {
    return $this->findByStatus('pending');
  }
}
