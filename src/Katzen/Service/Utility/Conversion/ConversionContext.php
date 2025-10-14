<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Item;

final class ConversionContext
{
    public function __construct(
      public readonly ?Item $item = null,
      public readonly ?float $temperature = null,
      public readonly ?float $density = null,
      public readonly ?string $note = null,
    ) {}
}
