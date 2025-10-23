<?php

namespace App\Katzen\Service\Utility;

use App\Katzen\Entity\KatzenUser;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Repository\InvoiceRepository;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\StockTargetRepository;
use Psr\Cache\CacheItemPoolInterface;

final class DashboardMetricsService
{
  private const CACHE_TTL = 300; // 5 minutes
  private const CACHE_PREFIX = 'dashboard_metrics';
  
  public function __construct(
    private OrderRepository $orderRepo,
    private StockTargetRepository $stockRepo,
    private InvoiceRepository $invoiceRepo,
    private CustomerRepository $customerRepo,
    private CacheItemPoolInterface $cache,
  ) {}
  
  /**
   * Get cached dashboard metrics for a user
   */
  public function getMetrics(?KatzenUser $user = null): DashboardMetrics
  {
    $cacheKey = $this->getCacheKey($user);
    $item = $this->cache->getItem($cacheKey);
    
    if ($item->isHit()) {
      return $item->get();
    }
    
    $metrics = $this->computeMetrics($user);
    
    $item->set($metrics);
    $item->expiresAfter(self::CACHE_TTL);
    $this->cache->save($item);
    
    return $metrics;
  }
  
  /**
   * Invalidate cache when important events happen
   */
  public function invalidate(?KatzenUser $user = null): void
  {
    $this->cache->deleteItem($this->getCacheKey($user));
  }
  
  private function computeMetrics(?KatzenUser $user): DashboardMetrics
  {
    $pendingOrders = $this->countOrdersByStatus(['pending', 'ready']);
    $upcomingOrders = $this->countUpcomingOrders(days: 2);
    $overdueInvoices = $this->countOverdueInvoices();
    $lowStockCount = $this->countLowStock();
    $activeCustomers = $this->countActiveCustomers(days: 30);
    $todayRevenue = $this->calculateRevenueForDate(new \DateTimeImmutable('today'));
      
    $stockHealth = $this->determineStockHealth($lowStockCount);
    $orderLoad = $this->determineOrderLoad($pendingOrders);
    
    return new DashboardMetrics(
      // Raw numbers
      pendingOrderCount: $pendingOrders,
      upcomingOrderCount: $upcomingOrders,
      lowStockItemCount: $lowStockCount,
      overdueInvoiceCount: $overdueInvoices,
      activeCustomerCount: $activeCustomers,
      todayRevenue: $todayRevenue,
      
      // Derived and designed metrics
      stockHealth: $stockHealth,
      orderLoad: $orderLoad,
      needsAttention: ($pendingOrders + $overdueInvoices) > 0,
      
      computedAt: new \DateTimeImmutable(),
    );
  }
  
  /**
   * Count orders by status values
   */
  private function countOrdersByStatus(array $statuses): int
  {
    $qb = $this->orderRepo->createQueryBuilder('o');
    
    return (int) $qb
      ->select('COUNT(o.id)')
      ->where($qb->expr()->in('o.status', ':statuses'))
      ->setParameter('statuses', $statuses)
      ->getQuery()
      ->getSingleScalarResult();
  }
  
  /**
   * Count orders that are upcoming within the specified number of days
   * Business rule: "upcoming" means orders with a scheduled_at within X days from now
   */
  private function countUpcomingOrders(int $days): int
  {
    $now = new \DateTimeImmutable();
    $futureDate = $now->modify("+{$days} days");
    
    $qb = $this->orderRepo->createQueryBuilder('o');
    
    return (int) $qb
      ->select('COUNT(o.id)')
      ->where('o.scheduled_at BETWEEN :start AND :end')
      ->setParameter('start', $now)
      ->setParameter('end', $futureDate)
      ->getQuery()
      ->getSingleScalarResult();
  }
  
  /**
   * Count overdue invoices
   * Business rule: "overdue" means unpaid invoices past their due date
   */
  private function countOverdueInvoices(): int
  {
    $now = new \DateTimeImmutable();
    
    $qb = $this->invoiceRepo->createQueryBuilder('i');
    
    return (int) $qb
      ->select('COUNT(i.id)')
      ->where('i.due_date < :now')
      ->andWhere('i.status != :paid')
      ->setParameter('now', $now)
      ->setParameter('paid', 'paid')
      ->getQuery()
      ->getSingleScalarResult();
  }
  
  /**
   * Count stock items below their target threshold
   * Business rule: "low stock" means current quantity < minimum threshold
   */
  private function countLowStock(): int
  {
    $qb = $this->stockRepo->createQueryBuilder('st');
    
    return (int) $qb
      ->select('COUNT(st.id)')
      ->where('st.current_qty < st.reorder_point')
      ->getQuery()
      ->getSingleScalarResult();
  }
  
  /**
   * Count active customers within the specified number of days
   * Business rule: "active" means customers with at least one order in the last X days
   */
  private function countActiveCustomers(int $days): int
  {
    $cutoffDate = (new \DateTimeImmutable())->modify("-{$days} days");
    
    $qb = $this->customerRepo->createQueryBuilder('c');
    
    return (int) $qb
      ->select('COUNT(DISTINCT c.id)')
      ->innerJoin('c.orders', 'o')
      ->where('o.created_at >= :cutoff')
      ->setParameter('cutoff', $cutoffDate)
      ->getQuery()
      ->getSingleScalarResult();
  }
  
  /**
   * Calculate total revenue for a specific date
   * Business rule: sum all completed order totals for the given date
   */
  private function calculateRevenueForDate(\DateTimeInterface $date): float
  {
    $startOfDay = (new \DateTimeImmutable($date->format('Y-m-d')))->setTime(0, 0, 0);
    $endOfDay = $startOfDay->setTime(23, 59, 59);
    
    $qb = $this->orderRepo->createQueryBuilder('o');
    
    $result = $qb
      ->select('SUM(o.total_amount)')
      ->where('o.created_at BETWEEN :start AND :end')
      ->andWhere('o.status = :completed')
      ->setParameter('start', $startOfDay)
      ->setParameter('end', $endOfDay)
      ->setParameter('completed', 'completed')
      ->getQuery()
      ->getSingleScalarResult();
    
    return (float) ($result ?? 0.0);
  }
  
  /**
   * Determine stock health status based on low stock count
   */
  private function determineStockHealth(int $lowStockCount): string
  {
    return match(true) {
      $lowStockCount === 0 => 'good',
      $lowStockCount < 5 => 'warning',
      default => 'critical',
    };
  }
  
  /**
   * Determine order load status based on pending order count
   */
  private function determineOrderLoad(int $pendingOrders): string
  {
    return match(true) {
      $pendingOrders === 0 => 'quiet',
      $pendingOrders < 5 => 'normal',
      $pendingOrders < 10 => 'busy',
      default => 'slammed',
    };
  }
    
  private function getCacheKey(?KatzenUser $user): string
  {
    $userId = $user?->getId() ?? 'guest';
    return self::CACHE_PREFIX . "_{$userId}";
  }
}
