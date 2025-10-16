<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Payment::class);
  }

  public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function add(Payment $payment): void
  {
    $this->getEntityManager()->persist($payment);
  }
  
  public function save(Payment $payment): void
  {
    $this->getEntityManager()->persist($payment);
    $this->getEntityManager()->flush();
  }

  public function findByDateRange(\DateTimeInterface$from, \DateTimeInterface $to): array
  {
    return $this->createQueryBuilder('p')
                 ->andWhere('p.payment_date >= :from')
                 ->setParameter('from', $from)
                 ->andWhere('p.payment_date <= :to')
                 ->setParameter('to', $to)      
                 ->orderBy('p.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findByStatus($value): array
  {
    return $this->createQueryBuilder('p')
                 ->andWhere('p.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('p.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findFailed(): array
  {
    return $this->findByStatus('failed');
  }
}
