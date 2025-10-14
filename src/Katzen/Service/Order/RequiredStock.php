<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\StockTarget;

final class RequiredStock
{
  /** @param list<RequiredStockLine> $lines */
  public function __construct(
    public readonly StockTarget $target,
    public array $lines = [],
    public float $totalBaseQty = 0.0
  ) {}
}
