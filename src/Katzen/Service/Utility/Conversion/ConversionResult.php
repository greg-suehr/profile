<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;

final class ConversionResult
{
  public function __construct(
    public readonly float $value,
    public readonly Unit $fromUnit,
    public readonly Unit $toUnit,
    public readonly float $factor,
    public readonly array $path = [],
    public readonly ?string $warning = null
  ) {}
}
