<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;

interface RoundingPolicyInterface
{
  public function apply(Unit $to, float $value): float;
}
