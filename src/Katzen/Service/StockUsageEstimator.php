<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\StockTarget;
use App\Katzen\Repository\StockTransactionRepository;

final class StockUsageEstimator
{
  public function __construct(private StockTransactionRepository $txRepo) {}
  
  public function estimatedDailyUse(StockTarget $target, int $days = 14): ?float
  {
    $since = (new \DateTimeImmutable())->modify("-{$days} days");
    
    $totalConsumed = $this->txRepo->sumConsumedSince($target, $since);
    if ($totalConsumed === null) return null;
    
    return max(0.0, $totalConsumed / max(1, $days));
  }
}
