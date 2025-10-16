<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\InvoiceLineItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceLineItem>
 */
class InvoiceLineItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, InvoiceLineItem::class);
  }

  public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function save(InvoiceLineItem $item): void
  {
    $this->getEntityManager()->persist($item);
    $this->getEntityManager()->flush();
  }

  public function findByDateRange(
    \DateTimeInterface$from,
    \DateTimeInterface $to,
    string $date_field='invoice_date'
  ): array
  {
    return $this->createQueryBuilder('ili')
                 ->join('ili.invoice', 'i')
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
    return $this->createQueryBuilder('ili')
                 ->join('ili.invoice', 'i')
                 ->andWhere('i.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('i.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findOpen(): array
  {
    return $this->findByStatus(['sent', 'overdue']);
  }
}
