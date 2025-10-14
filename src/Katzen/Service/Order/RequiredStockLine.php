<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Unit;

final class RequiredStockLine
{
  public function __construct(
    public readonly RecipeIngredient $ingredient,
    public readonly float $requestedQty,
    public readonly Unit $ingredientUnit,
    public ?float $convertedQty = null,
    public array $conversionErrors = []
  ) {}
}
