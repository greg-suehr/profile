<?php

namespace App\Katzen\Service\Utility;

final class DashboardMetrics
{
  public function __construct(
    public readonly int $pendingOrderCount,
    public readonly int $upcomingOrderCount,
    public readonly int $lowStockItemCount,
    public readonly int $overdueInvoiceCount,
    public readonly int $activeCustomerCount,
    public readonly float $todayRevenue,
        
    // Derived and designed metrics
    public readonly string $stockHealth,     // 'good' | 'warning' | 'critical'
    public readonly string $orderLoad,       // 'quiet' | 'normal' | 'busy' | 'slammed'
    public readonly bool $needsAttention,
        
    public readonly \DateTimeImmutable $computedAt,
  ) {}
    
  public function isStale(int $maxAgeSeconds = 300): bool
  {
    $age = (new \DateTimeImmutable())->getTimestamp() - $this->computedAt->getTimestamp();
    return $age > $maxAgeSeconds;
  }
    
  public function getTotalActionableCount(): int
  {
    return $this->pendingOrderCount 
      + $this->overdueInvoiceCount 
      + $this->lowStockItemCount;
  }
}
