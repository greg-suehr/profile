<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\{Order, OrderItem};
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\Inventory\StockTargetAutogenerator;
use App\Katzen\Service\Utility\Conversion\ConversionContext;
use App\Katzen\Service\Utility\Conversion\ConversionHelper;
use App\Katzen\Service\Response\ServiceResponse;

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
   * @return ServiceResponse
   *           data: array{requirements: list<RequiredStock>, errors: list<string>, meta: array}
   */
  public function plan(array $sources, string $purpose = 'consume', string $groupBy = 'stockTarget'): ServiceResponse
  {
    try {
      $requirements = [];  
      $detail = [];
      $errors  = [];
      
      // Customer orders
      foreach ($sources['orders'] ?? [] as $order) {
        foreach ($order->getOrderItems() as $oi) {
          # TODO: more robust breakdown of variants and modifiers
          $sellable = $oi->getSellable();
          
          if (!$sellable instanceof Sellable) {
            $errors[] = sprintf('OrderItem %d has no Sellable linked.', $oi->getId());
            continue;
          }
          
          $lineQty = (float) ($oi->getQuantity() ?? 0);
          if ($lineQty <= 0) {
            $errors[] = sprintf('OrderItem %d has non-positive quantity.', $oi->getId());
            continue;
          }
          
          // TODO: handle sellable variant multiplies? or already handled with direct variint link?
          
          $this->accumulateSellableComponents(
            $requirements,
            $detail,
            $oi,
            $sellable,
            $lineQty * $sellable->getPortionMultiplier(),
          );

          $modifiers = $oi->getModifiers() ?? [];
          foreach ($this->iterResolvedModifierSellables($modifiers) as $modSellableTuple) {
            /** @var Sellable $modSellable */
            [$modSellable, $modPortionMult] = $modSellableTuple;
            $effectiveQty = $lineQty * (float)$modPortionMult;
            $this->accumulateSellableComponents(
              $requirements,
              $detail,
              $oi,
              $modSellable,
              $effectiveQty
            );
          }
        }
      }
      
      // Production batches
      foreach ($sources['recipes'] ?? [] as $r) {
        $lines += $this->consumeRecipe($r['recipe'], (float)$r['servings'], $purpose, $groupBy, $grouped, $errors);
      }
      
      return ServiceResponse::success(
        data: ['requirements' => $requirements],
        message: 'Aggregated stock requirements from Sellables.',
        metadata: ['errors' => $errors, 'detail' => $detail]
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to aggregate requirements: ' . $e->getMessage()],
        message: 'Unhandled exception while aggregating stock requirements.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Sum all Sellable->components into requirements for a given effectiveQty.
   */
  private function accumulateSellableComponents(
    array &$requirements,
    array &$detail,
    OrderItem $item,
    Sellable $sellable,
    float $effectiveQty
  ): void {
    /** @var SellableComponent $component */
    foreach ($sellable->getComponents() as $component) {
      $target = $component->getTarget();
      if (!$target instanceof StockTarget) {
        // Skip silently; it’s a data error but we don’t want to break the whole order
        continue;
      }

      $perPortionQty = (float) ($component->getQuantityMultiplier() ?? 0.0);
      $lineRequirement = $perPortionQty * $effectiveQty;
      
      $targetId = $target->getId();
      if (!isset($requirements[$targetId])) {
        $requirements[$targetId] = 0.0;
      }
      $requirements[$targetId] += $lineRequirement;
      
      // Diagnostics
      $detail[$targetId][] = [
        'orderItemId' => $item->getId(),
        'sellableId'  => $sellable->getId(),
        'componentId' => $component->getId(),
        'qty'         => $lineRequirement,
      ];
    }
  }

  /**
   * Resolve OrderItem modifiers to iterable of [Sellable, portionMultiplier].
   *
   * Notes:
   * - If your modifiers are persisted as Sellable entities already, just yield them with 1.0.
   * - If they’re stored as arrays or IDs, inject a resolver/repository here.
   * - Supports future per-modifier portion logic (e.g., “extra shot” doubles).
   */
  private function iterResolvedModifierSellables(array $modifiers): \Generator
  {
    foreach ($modifiers as $mod) {
      if ($mod instanceof Sellable) {
        yield [$mod, 1.0];
      } elseif (is_array($mod)) {
        // Example shape: ['sellable' => Sellable|int, 'portion_mult' => 1.0]
        $portionMult = (float)($mod['portion_mult'] ?? 1.0);
        $s = $mod['sellable'] ?? null;
        if ($s instanceof Sellable) {
          yield [$s, $portionMult];
        }
        // If $s is an ID, you could resolve here via a repository (kept out to avoid adding dependencies)
      }
      // else ignore gracefully
    }
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
