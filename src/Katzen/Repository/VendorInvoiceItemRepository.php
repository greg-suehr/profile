<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\VendorInvoiceItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VendorInvoiceItem>
 */
class VendorInvoiceItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, VendorInvoiceItem::class);
  }

  public function save(VendorInvoiceItem $entity, bool $flush = true): void
  {
    $this->getEntityManager()->persist($entity);
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find items with price variance flags
   * 
   * @return VendorInvoiceItem[]
   */
  public function findFlaggedVariances(): array
  {
    return $this->createQueryBuilder('vii')
            ->join('vii.vendor_invoice', 'vi')
            ->where('vii.variance_flagged = :true')
            ->andWhere('vi.status != :void')
            ->setParameter('true', true)
            ->setParameter('void', 'void')
            ->orderBy('vii.price_variance_pct', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find items by stock target
   * 
   * @return VendorInvoiceItem[]
   */
  public function findByStockTarget(
    StockTarget $stockTarget,
    ?\DateTimeInterface $since = null,
    int $limit = 50
  ): array
  {
    $qb = $this->createQueryBuilder('vii')
            ->join('vii.vendor_invoice', 'vi')
            ->where('vii.stock_target = :target')
            ->setParameter('target', $stockTarget)
            ->orderBy('vi.invoice_date', 'DESC')
            ->setMaxResults($limit);

    if ($since) {
      $qb->andWhere('vi.invoice_date >= :since')
          ->setParameter('since', $since);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get average purchase price for a stock target
   */
  public function getAveragePurchasePrice(
    StockTarget $stockTarget,
    int $days = 30
  ): ?float
  {
    $since = (new \DateTime())->modify("-{$days} days");

    $result = $this->createQueryBuilder('vii')
            ->select('AVG(vii.unit_price) as avg_price')
            ->join('vii.vendor_invoice', 'vi')
            ->where('vii.stock_target = :target')
            ->andWhere('vi.invoice_date >= :since')
            ->andWhere('vi.status != :void')
            ->setParameter('target', $stockTarget)
            ->setParameter('since', $since)
            ->setParameter('void', 'void')
            ->getQuery()
            ->getOneOrNullResult();

    return $result['avg_price'] ? (float)$result['avg_price'] : null;
  }

  /**
   * Get total spending by stock target
   */
  public function getTotalSpendingByTarget(
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array
  {
    $results = $this->createQueryBuilder('vii')
            ->select('vii.stock_target', 'SUM(vii.line_total) as total_spent')
            ->join('vii.vendor_invoice', 'vi')
            ->where('vi.invoice_date >= :from')
            ->andWhere('vi.invoice_date <= :to')
            ->andWhere('vi.status != :void')
            ->andWhere('vii.stock_target IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('void', 'void')
            ->groupBy('vii.stock_target')
            ->orderBy('total_spent', 'DESC')
            ->getQuery()
            ->getResult();

    $spending = [];
    foreach ($results as $result) {
      $spending[] = [
        'stock_target' => $result['stock_target'],
        'total_spent' => (float)$result['total_spent'],
      ];
    }
    
    return $spending;
  }
}
