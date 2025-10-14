<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\Order;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\Inventory\StockTargetAutogenerator;
use App\Katzen\Service\Utility\Conversion\ConversionContext;
use App\Katzen\Service\Utility\Conversion\ConversionHelper;

final class RequirementsPlanner
{
  public function __construct(
    private OrderRepository $orders,
    private RecipeExpanderService $expander,
    private StockTargetAutogenerator $autogen,
    private ConversionHelper $convert
  ) {}

  /**
   * purpose: 'consume' | 'purchase' | 'prep'
   * groupBy: 'stockTarget' | 'item' | 'purchasingUnit'
   * sources: array of Orders and/or [recipe, servings]
   *
   * @param array{orders?:list<Order>, recipes?:list<array{recipe:Recipe,servings:float}>} $sources
   * @return array{requirements: list<RequiredStock>, errors: list<string>, meta: array}
   */
  public function plan(array $sources, string $purpose = 'consume', string $groupBy = 'stockTarget'): array
  {
      $grouped = []; // map<string, RequiredStock>
      $errors  = [];
      $lines   = 0;

      // Customer orders
      foreach ($sources['orders'] ?? [] as $order) {
        foreach ($order->getOrderItems() as $oi) {
          $recipe   = $oi->getRecipeListRecipeId();
          $servings = (float)($oi->getQuantity() ?? 1);
          if (!$recipe) { continue; }
          $lines += $this->consumeRecipe($recipe, $servings, $purpose, $groupBy, $grouped, $errors);
        }
      }
      
      // Production batches
      foreach ($sources['recipes'] ?? [] as $r) {
        $lines += $this->consumeRecipe($r['recipe'], (float)$r['servings'], $purpose, $groupBy, $grouped, $errors);
      }
      
      return [
        'requirements' => array_values($grouped),
        'errors'       => $errors,
        'meta'         => ['purpose'=>$purpose, 'groupBy'=>$groupBy, 'lines'=>$lines, 'groups'=>count($grouped)],
      ];
  }

  private function consumeRecipe(
    Recipe $recipe, float $servings, string $purpose, string $groupBy,
    array &$grouped, array &$errors
  ): int {
      $count = 0;
      foreach ($this->expander->expandRecipe($recipe, $servings) as $line) {
        $this->convertAndGroup($line, $purpose, $groupBy, $grouped, $errors);
        $count++;
      }
      return $count;
  }

  private function convertAndGroup(
    RequiredStockLine $line, string $purpose, string $groupBy,
    array &$grouped, array &$errors
  ): void {
      $ing    = $line->ingredient;
      $target = $this->autogen->getStockTarget($ing);
      
      $toUnit = $target?->getBaseUnit();
      if ($purpose === 'purchase') {
        $toUnit = $target?->getPurchasingUnit() ?? $toUnit; # TODO: add to entity
      } elseif ($purpose === 'prep') {
        $toUnit = $target?->getPrepUnit() ?? $toUnit; # TODO: add to entity
      }
      
      $localErrors = [];
      $converted   = null;
      
      if (!$target || !$toUnit) {
        $localErrors[] = 'Missing StockTarget or target unit';
      } else {
        $ctx = new ConversionContext(
          item: $target->getRecipe() ?? $target->getItem(),
          temperature: null,
          density: null,
          note: $purpose
        );
        $converted = $this->convert->tryConvert(
          qty: $line->requestedQty,
          from: $line->ingredientUnit,
                to: $toUnit,
          ctx: $ctx,
          errors: $localErrors,
          trigger: ['ingredient_id'=>$ing->getId(),'target_id'=>$target->getId(),'purpose'=>$purpose]
        );
      }
      
      $line->convertedQty    = $converted;
      $line->conversionErrors = $localErrors;
      
      $key = match ($groupBy) {
        'item'           => 'item:' . ($target?->getItem()?->getId() ?? 'missing'),
        'purchasingUnit' => 'pu:' . ($target?->getPurchasingUnit()?->getId() ?? $toUnit?->getId() ?? 'missing')
                                . ':item:' . ($target?->getItem()?->getId() ?? 'missing'),
        default          => 'target:' . ($target?->getId() ?? 'missing'),
      };
      
      if (!isset($grouped[$key])) {
        $grouped[$key] = new RequiredStock(
          target: $target ?? $this->nullTarget(),
          lines: [],
          totalBaseQty: 0.0
        );
      }
      $grouped[$key]->lines[] = $line;
      if ($converted !== null) {
        $grouped[$key]->totalBaseQty += $converted;
      }
      
      array_push($errors, ...$localErrors);
    }
  
  private function nullTarget(): StockTarget
  {
        return new class extends StockTarget {};
  }
}
