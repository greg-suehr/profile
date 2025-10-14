<?php

namespace App\Katzen\Service\Utility\Conversion;

final class ConversionPlan
{
  /** @param ConversionEdge[] $edges */
  public function __construct(
    public readonly float $factor,
    public readonly array $edges,
    public readonly ?string $warning = null,
    public readonly array $metadata = [],
  ) {}
}
