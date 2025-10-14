<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;

final class ConversionEdge
{
    public function __construct(
      public readonly Unit $from,
      public readonly Unit $to,
      public readonly float $factor,
      public readonly string $type = 'global', // 'global' | 'item' | 'density'
      public readonly array $metadata = [],
    ) {}
}
