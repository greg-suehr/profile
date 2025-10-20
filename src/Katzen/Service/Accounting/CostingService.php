<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Order;
use App\Katzen\Repository\StockTransactionRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\Service\Utility\Conversion\ConversionHelper;
use Doctrine\ORM\EntityManagerInterface;
  

final class CostingService
{
  public function __construct(
    private EntityManagerInterface $em,
    private StockTransactionRepository $txnRepo,
    private StockTargetRepository $targetRepo,
    private RecipeExpanderService $expander,
    private ConversionHelper $converter,
  ) {}

  // ============================================
  // INVENTORY COSTING
  // ============================================
    
  /**
   * Calculate the cost of consuming a specific quantity of stock
   * 
   * @param string $method 'weighted_average'|'fifo'|'lifo'|'standard'
   */
  public function getInventoryCost(
    StockTarget $target, 
    float $qty,
    ?string $method = null,
    ?\DateTimeInterface $asOf = null
  ): float
  {
    $method = $method ?? $this->getDefaultCostingMethod();
    $breakdown = $this->getInventoryCostBreakdown($target, $qty, $method);
    return array_sum(array_column($breakdown, 'total'));
  }
  
  /**
   * Get inventory cost broken down by cost layer for FIFO and LIFO costing.
   * 
   * @return array<array{transaction_id: int, qty: float, unit_cost: float, total: float}>
   */
  public function getInventoryCostBreakdown(
    StockTarget $target,
    float $qty,
    string $method = 'fifo'
  ): array
  {
    $transactions = $this->txnRepo->findStockMovements(
      $target,
      'inbound',
      ordered: 'ASC'
    );
    
    $breakdown = [];
    $remaining = $qty;
    
    foreach ($transactions as $txn) {
      if ($remaining <= 0) break;
      
      $available = $txn->getQuantity();
      $consumed = min($available, $remaining);
      
      $breakdown[] = [
        'transaction_id' => $txn->getId(),
        'qty' => $consumed,
        'unit_cost' => $txn->getUnitCost(),
        'total' => $consumed * $txn->getUnitCost(),
      ];
      
      $remaining -= $consumed;
    }
    
    return $breakdown;
  }
    
  /**
   * Get current unit cost for an item.
   */
  public function getUnitCost(
    StockTarget $target,
    ?string $method = null
  ): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Total value of all inventory on hand
   */
  public function getInventoryValuation(
    ?string $method = null,
    ?\DateTimeInterface $asOf = null
  ): float
  {
    throw new \NotImplementedException;
  }      
  
    
  // ============================================
  // RECIPE COSTING
  // ============================================
    
  /**
   * Calculate the current actual cost to make one serving of a recipe
   */
  public function getRecipeCost(
    Recipe $recipe,
    float $servings = 1.0,
      ?string $method = null
  ): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Get detailed breakdown of recipe costs by ingredient
   * 
   * @return array<array{ingredient: RecipeIngredient, qty: float, unit_cost: float, total: float}>
   */
  public function getRecipeCostBreakdown(
    Recipe $recipe,
    float $servings = 1.0
  ): array
  {
    throw new \NotImplementedException;
  }

  /**
   * Get or set the standard cost for a recipe (target cost for planning)
   */
  public function getStandardRecipeCost(Recipe $recipe): ?float
  {
    throw new \NotImplementedException;
  }
  public function setStandardRecipeCost(Recipe $recipe, float $cost): void
  {
    throw new \NotImplementedException;
  }
    
  /**
   * Calculate variance between actual and standard cost
   * 
   * @return array{actual: float, standard: float, variance: float, variance_pct: float}
   */
  public function getRecipeCostVariance(Recipe $recipe): array
  {
    throw new \NotImplementedException;
  }
  
  // ============================================
  // ORDER COSTING
  // ============================================
  
  /**
   * Calculate total COGS for an order
   */
  public function getOrderCost(Order $order): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Get order cost broken down by recipe
   * 
   * @return array<array{recipe: Recipe, servings: float, unit_cost: float, total: float}>
   */
  public function getOrderCostBreakdown(Order $order): array
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Calculate order profitability
   * 
   * @return array{revenue: float, cogs: float, gross_profit: float, margin_pct: float}
   */
  public function getOrderProfitability(Order $order): array
  {
    throw new \NotImplementedException;
  }
  
  // ============================================
  // BATCH/PREP COSTING
  // ============================================
  
  /**
   * Calculate cost to produce a batch (Recipe used as intermediate product)
   */
  public function getBatchCost(Recipe $recipe, float $servings): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Allocate batch cost to individual units (for Recipe -> StockTarget flow)
   */
  public function allocateBatchCost(
    Recipe $recipe,
    float $totalCost,
    float $yieldQuantity
  ): float
  {
    throw new \NotImplementedException;
  }
  
  // ============================================
  // VARIANCE & ANALYSIS
  // ============================================
  
  /**
   * Track cost changes over time for an item
   * 
   * @return array<array{date: \DateTimeInterface, unit_cost: float}>
   */
  public function getCostHistory(
    StockTarget $target,
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Calculate variance due to waste/spoilage
   */
  public function getWasteVariance(
    StockTarget $target,
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Calculate price variance (purchase price vs standard cost)
   * 
   * @return array{actual_cost: float, standard_cost: float, variance: float}
   */
  public function getPurchasePriceVariance(
    StockTransaction $purchaseTxn
  ): array
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Calculate usage variance (actual qty used vs expected)
   */
  public function getUsageVariance(
    Recipe $recipe,
    float $servingsProduced,
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array
  {
    throw new \NotImplementedException;
  }
  
  // ============================================
    // COST ROLL-UPS
    // ============================================
    
  /**
   * Calculate full cost including sub-recipes
   * (e.g., if "Lasagna" uses "Marinara Sauce" which is also a Recipe)
   */
  public function getFullRecipeCost(
    Recipe $recipe,
    float $servings = 1.0,
    int $maxDepth = 5
  ): float
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Get all cost components in a recipe tree
   * 
   * @return array Tree structure of costs at each level
   */
  public function getRecipeCostTree(Recipe $recipe): array
  {
    throw new \NotImplementedException;
  }
    
  // ============================================
  // CONFIGURATION
  // ============================================
  
  /**
   * Get the costing method configured for a specific stock target or item class.
   */
  public function getCostingMethod(StockTarget $target): string
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Set costing method for a stock target.
   */
  public function setCostingMethod(StockTarget $target, string $method): void
  {
    throw new \NotImplementedException;
  }
  
  /**
   * Get global default costing method
   */
  public function getDefaultCostingMethod(): string
  {
    throw new \NotImplementedException;
  }
}
