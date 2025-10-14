<?php

namespace App\Katzen\Service\Utility\Conversion\Policy;

use App\Katzen\Entity\Unit;
use App\Katzen\Service\Utility\Conversion\RoundingPolicyInterface;

final class RoundingPolicy implements RoundingPolicyInterface
{
  public function __construct(private int $scale = 6, private int $displayScale = 3) {}
  
  public function apply(Unit $to, float $value): float
  {
    return round($value, $this->displayScale);
  }
}
