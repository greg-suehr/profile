<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\VendorInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VendorInvoice>
 */
class VendorInvoiceRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, VendorInvoice::class);
  }

  public function save(VendorInvoice $entity, bool $flush = true): void
  {
    $this->getEntityManager()->persist($entity);
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find overdue invoices
   * 
   * @return VendorInvoice[]
   */
  public function findOverdue(): array
  {
    $today = new \DateTime();

    return $this->createQueryBuilder('vi')
            ->where('vi.due_date < :today')
            ->andWhere('vi.status IN (:statuses)')
            ->andWhere('vi.total_amount > vi.amount_paid')
            ->setParameter('today', $today)
            ->setParameter('statuses', ['pending', 'approved', 'partial'])
            ->orderBy('vi.due_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

  /**
   * Find invoices by vendor
   * 
   * @return VendorInvoice[]
   */
  public function findByVendor(Vendor $vendor, ?string $status = null, int $limit = 50): array
  {
    $qb = $this->createQueryBuilder('vi')
            ->where('vi.vendor = :vendor')
            ->setParameter('vendor', $vendor)
            ->orderBy('vi.invoice_date', 'DESC')
            ->setMaxResults($limit);
    
    if ($status) {
      $qb->andWhere('vi.status = :status')
         ->setParameter('status', $status);
    }
    
    return $qb->getQuery()->getResult();
  }

  /**
   * Find unreconciled invoices
   * 
   * @return VendorInvoice[]
   */
  public function findUnreconciled(): array
  {
    return $this->createQueryBuilder('vi')
            ->where('vi.reconciled = :false')
            ->andWhere('vi.purchase IS NOT NULL')
            ->andWhere('vi.status != :void')
            ->setParameter('false', false)
            ->setParameter('void', 'void')
            ->orderBy('vi.invoice_date', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find invoices with variances
   * 
   * @return VendorInvoice[]
   */
  public function findWithVariances(float $minVariance = 0.01): array
  {
    return $this->createQueryBuilder('vi')
            ->where('ABS(vi.variance_total) >= :min_variance')
            ->setParameter('min_variance', $minVariance)
            ->orderBy('ABS(vi.variance_total)', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get invoices pending approval
   * 
   * @return VendorInvoice[]
   */
  public function findPendingApproval(): array
  {
    return $this->createQueryBuilder('vi')
            ->where('vi.approval_status = :pending')
            ->andWhere('vi.status != :void')
            ->setParameter('pending', 'pending')
            ->setParameter('void', 'void')
            ->orderBy('vi.invoice_date', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get total unpaid amount for a vendor
   */
  public function getUnpaidTotal(Vendor $vendor): float
  {
    $result = $this->createQueryBuilder('vi')
            ->select('SUM(vi.total_amount - vi.amount_paid) as unpaid')
            ->where('vi.vendor = :vendor')
            ->andWhere('vi.status IN (:statuses)')
            ->setParameter('vendor', $vendor)
            ->setParameter('statuses', ['pending', 'approved', 'partial'])
            ->getQuery()
            ->getOneOrNullResult();
    
    return $result['unpaid'] ? (float)$result['unpaid'] : 0.0;
  }

  /**
   * Find invoices by date range
   * 
   * @return VendorInvoice[]
   */
  public function findByDateRange(
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    ?string $status = null
  ): array
  {
    $qb = $this->createQueryBuilder('vi')
            ->where('vi.invoice_date >= :from')
            ->andWhere('vi.invoice_date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('vi.invoice_date', 'DESC');

    if ($status) {
      $qb->andWhere('vi.status = :status')
         ->setParameter('status', $status);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get summary statistics
   * 
   * @return array{
   *   total_invoices: int,
   *   total_amount: float,
   *   amount_paid: float,
   *   amount_due: float,
   *   overdue_count: int,
   *   overdue_amount: float
   * }
   */
  public function getSummaryStats(): array
  {
    $qb = $this->createQueryBuilder('vi')
            ->select(
                'COUNT(vi.id) as total_invoices',
                'SUM(vi.total_amount) as total_amount',
                'SUM(vi.amount_paid) as amount_paid',
                'SUM(vi.total_amount - vi.amount_paid) as amount_due'
            )
            ->where('vi.status != :void')
            ->setParameter('void', 'void');

    $result = $qb->getQuery()->getOneOrNullResult();

    $today = new \DateTime();
    $overdueQb = $this->createQueryBuilder('vi')
            ->select(
              'COUNT(vi.id) as overdue_count',
              'SUM(vi.total_amount - vi.amount_paid) as overdue_amount'
            )
            ->where('vi.due_date < :today')
            ->andWhere('vi.status IN (:statuses)')
            ->andWhere('vi.total_amount > vi.amount_paid')
            ->setParameter('today', $today)
            ->setParameter('statuses', ['pending', 'approved', 'partial']);
    
    $overdueResult = $overdueQb->getQuery()->getOneOrNullResult();
    
    return [
      'total_invoices' => (int)($result['total_invoices'] ?? 0),
      'total_amount' => (float)($result['total_amount'] ?? 0.0),
      'amount_paid' => (float)($result['amount_paid'] ?? 0.0),
      'amount_due' => (float)($result['amount_due'] ?? 0.0),
      'overdue_count' => (int)($overdueResult['overdue_count'] ?? 0),
      'overdue_amount' => (float)($overdueResult['overdue_amount'] ?? 0.0),
    ];
  }
}
