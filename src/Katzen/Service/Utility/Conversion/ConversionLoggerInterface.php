<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;

interface ConversionLoggerInterface
{
  /**
   * @param array<int,ConversionEdge> $edges
   * @param array<string,mixed> $trigger  Arbitrary "what did this" (order_id, recipe_id)
   * @param array<string,mixed> $context  Anything from ConversionContext, string notes
   */
  public function log(
    float $originalValue,
    float $convertedValue,
    Unit $from,
    Unit $to,
    float $factor,
    array $edges,
    array $trigger = [],
    array $context = []
  ): void;
}
