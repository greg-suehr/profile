<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Invoice::class);
  }

  public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function save(Invoice $invoice): void
  {
    $this->getEntityManager()->persist($invoice);
    $this->getEntityManager()->flush();
  }

  public function findByDateRange(
    \DateTimeInterface$from,
    \DateTimeInterface $to,
    string $date_field='invoice_date'    
  ): array
  {
    return $this->createQueryBuilder('i')
                 ->andWhere('i.:from_date_field >= :from')
                 ->setParameter('from_date_field', $date_field)
                 ->setParameter('from', $from)
                 ->andWhere('i.:to_date_field <= :to')
                 ->setParameter('to_date_field', $date_field)      
                 ->setParameter('to', $to)      
                 ->orderBy('i.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findByStatus($value): array
  {
    return $this->createQueryBuilder('i')
                 ->andWhere('i.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('i.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findOpen(): array
  {
    return $this->findByStatus('pending');
  }
}
