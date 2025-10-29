<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\PriceHistory;
use App\Katzen\Entity\PlateCosting;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeCostSnapshot;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Vendor;
use App\Katzen\Repository\PriceHistoryRepository;
use App\Katzen\Repository\PlateCostingRepository;
use App\Katzen\Repository\RecipeCostSnapshotRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\StockLotRepository;
use App\Katzen\Repository\StockTargetRepository;

use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\Inventory\SupplyResolver;
use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\Service\Utility\Conversion\ConversionHelper;

use Doctrine\ORM\EntityManagerInterface;
  

final class CostingService
{
  public function __construct(
    private EntityManagerInterface $em,
    private PriceHistoryRepository $priceHistoryRepo,
    private PlateCostingRepository $plateCostingRepo,
    private RecipeCostSnapshotRepository $snapshotRepo,
    private RecipeRepository $recipeRepo,
    private StockTargetRepository $targetRepo,
    private StockLotRepository $lotRepo,
    private RecipeExpanderService $expander,
    private SupplyResolver $supply,
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
   * Get inventory cost, broken down by cost layer for FIFO and LIFO costing.
   *
   * @param StockTarget $target
   * @param float $qty the quantity to cost
   * @param string $method : 'fifo', 'standard', 'weighted_average'
   * 
   * @return array<array{transaction_id: int, qty: float, unit_cost: float, total: float}>
   */
  public function getInventoryCostBreakdown(
    StockTarget $target,
    float $qty,
    string $method = 'fifo'
  ): array
  {
    if ($method === 'weighted_average') {
      return $this->getWeightedAverageCostBreakdown($target, $qty);
    }
        
    if ($method === 'standard') {
      return $this->getStandardCostBreakdown($target, $qty);
    }

    # Continue for FIFO costing
    $lots = $this->lotRepo->findLotsForCosting($target, 'FIFO', onlyAvailable: true);
    
    $breakdown = [];
    $remaining = $qty;
    
    foreach ($lots as $lot) {
      if ($remaining <= 0) break;
      
      $available = $lot->getCurrentQty() - $lot->getReservedQty();
      $consumed = min($available, $remaining);
      
      $breakdown[] = [
        'lot_id' => $lot->getId(),
        'lot_number' => $lot->getLotNumber(),
        'qty' => $consumed,
        'unit_cost' => $lot->getUnitCost(),
        'total' => $consumed * $lot->getUnitCost(),
      ];
      
      $remaining -= $consumed;
    }
    
    return $breakdown;
  }

  /**
   * Get weighted average cost breakdown
   */
  private function getWeightedAverageCostBreakdown(StockTarget $target, float $qty): array
  {
   $lots = $this->lotRepo->findLotsForCosting($target, 'FIFO', onlyAvailable: true);
   
   $totalValue = 0.0;
   $totalQty = 0.0;
    
   foreach ($lots as $lot) {
      $totalValue += $lot->getCurrentQty() * $lot->getUnitCost();
      $totalQty += $lot->getCurrentQty();
    }
    
   $avgCost = $totalQty > 0 ? $totalValue / $totalQty : 0.0;
   # also   = $this->lotRepo->getWeightedAverageCost($target);
   
   return [[
     'transaction_id' => null,
     'qty' => $qty,
     'unit_cost' => $avgCost,
     'total' => $qty * $avgCost,
   ]];
  }
  
  /**
   * Get standard cost breakdown
   */
  private function getStandardCostBreakdown(StockTarget $target, float $qty): array
  {
    $standardCost = $target->getStandardCost() ?? 0.0;
        
    return [[
      'transaction_id' => null,
      'qty' => $qty,
      'unit_cost' => $standardCost,
      'total' => $qty * $standardCost,
    ]];
  }
    
  /**
   * Get current unit cost for an item.
   */
  public function getUnitCost(
    StockTarget $target,
    ?string $method = null
  ): float
  {
    $method = $method ?? $this->getDefaultCostingMethod();
    $breakdown = $this->getInventoryCostBreakdown($target, 1.0, $method);
        
    return $breakdown[0]['unit_cost'] ?? 0.0;
  }
  
  /**
   * Total value of all inventory on hand
   */
  public function getInventoryValuation(
    ?string $method = null,
    ?\DateTimeInterface $asOf = null
  ): float
  {
    $method = $method ?? $this->getDefaultCostingMethod();
    $targets = $this->targetRepo->findAll();
    
    $totalValue = 0.0;
    foreach ($targets as $target) {
      $qty = (float)$target->getCurrentQty();
      if ($qty > 0) {
        $totalValue += $this->getInventoryCost($target, $qty, $method, $asOf);
      }
    }
    
    return $totalValue;
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
    $breakdown = $this->getRecipeCostBreakdown($recipe, $servings, $method);
    return array_sum(array_column($breakdown, 'total'));
  }
  
  /**
   * Get detailed breakdown of recipe costs by ingredient
   * 
   * @return array<array{
   *   ingredient: RecipeIngredient,
   *   qty: float,
   *   unit_cost: float,
   *   total: float
   * }>
   */
  public function getRecipeCostBreakdown(
    Recipe $recipe,
    float $servings = 1.0
  ): array
  {
    $method = $method ?? $this->getDefaultCostingMethod();
    $lines = $this->expander->expandRecipe($recipe, $servings);
    
    $breakdown = [];
    foreach ($lines as $line) {
      $ingredient = $line->ingredient;
      
      // Resolve stock target from ingredient
      $stockTarget = $this->supply->resolve($ingredient);
      if (!$stockTarget) {
        continue; // Skip if we can't resolve to inventory
      }
      
      // Convert ingredient quantity to stock target's base unit
      $qty = $this->converter->convert(
        (float)$ingredient->getQuantity() * $servings,
        $ingredient->getUnit(),
        $stockTarget->getBaseUnit()
       );
      
      $unitCost = $this->getUnitCost($stockTarget, $method);

      $breakdown[] = [
        'ingredient' => $ingredient,
        'stock_target' => $stockTarget,
        'qty' => $qty,
        'unit_cost' => $unitCost,
        'total' => $qty * $unitCost,
      ];
    }
    
    return $breakdown;
  }

  /**
   * Get the standard cost for a recipe (target cost for planning)
   */
  public function getStandardRecipeCost(Recipe $recipe): ?float
  {
    // Look for most recent snapshot with calculation_method='standard'
    $snapshot = $this->snapshotRepo->findOneBy(
      ['recipe' => $recipe, 'calculation_method' => 'standard'],
      ['calculated_at' => 'DESC']
    );
        
    return $snapshot ? (float)$snapshot->getTotalCost() : null;
  }

  /**
   * Set the standard cost for a recipe
   */
  public function setStandardRecipeCost(Recipe $recipe, float $cost): void
  {
    $snapshot = new RecipeCostSnapshot();
    $snapshot->setRecipe($recipe);
    $snapshot->setTotalCost((string)$cost);
    $snapshot->setIngredientCost((string)$cost); // Simplified
    $snapshot->setServings((float)$recipe->getServings());
    $snapshot->setCostPerServing((string)($cost / max(1, $recipe->getServings())));
    $snapshot->setCalculationMethod('standard');
    $snapshot->setCalculatedAt(new \DateTime());
    
    $this->em->persist($snapshot);
    $this->em->flush();
  }
    
  /**
   * Calculate variance between actual and standard cost
   * 
   * @return array{actual: float, standard: float, variance: float, variance_pct: float}
   */
  public function getRecipeCostVariance(Recipe $recipe): array
  {
    $actualCost = $this->getRecipeCost($recipe, 1.0);
    $standardCost = $this->getStandardRecipeCost($recipe);
    
    $variance = null;
    $variancePct = null;
    
    if ($standardCost !== null && $standardCost > 0) {
      $variance = $actualCost - $standardCost;
      $variancePct = ($variance / $standardCost) * 100;
    }
    
    return [
      'actual' => $actualCost,
      'standard' => $standardCost,
      'variance' => $variance,
      'variance_pct' => $variancePct,
    ];
  }

  // ============================================
  // PLATE COSTING
  // ============================================

  /**
   * Update or create plate costing record for a recipe
   */
  public function updatePlateCosting(Recipe $recipe): PlateCosting
  {
    $plateCosting = $this->plateCostingRepo->findOneBy(['recipe' => $recipe]);
        
    if (!$plateCosting) {
      $plateCosting = new PlateCosting();
      $plateCosting->setRecipe($recipe);
      $plateCosting->setMenuItemName($recipe->getName());
    }

    $currentCost = $this->getRecipeCost($recipe, 1.0);
    $plateCosting->setCurrentCost((string)$currentCost);
        
    $currentPrice = $recipe->getPrice() ?? 0.0;
    $plateCosting->setCurrentPrice((string)$currentPrice);
        
    $foodCostPct = $currentPrice > 0 ? ($currentCost / $currentPrice) * 100 : 0.0;
    $plateCosting->setCurrentFoodCostPct((string)$foodCostPct);
        
    $targetFoodCostPct = $plateCosting->getTargetFoodCostPct() 
      ? (float)$plateCosting->getTargetFoodCostPct() 
      : 30.0; # TODO: pull from configuration
        
    $alertThreshold = $plateCosting->getAlertThresholdPct() 
      ? (float)$plateCosting->getAlertThresholdPct() 
      : 5.0; # TODO: pull from configuration
    
    if ($foodCostPct <= $targetFoodCostPct) {
      $status = 'on_target';
    } elseif ($foodCostPct <= $targetFoodCostPct + $alertThreshold) {
      $status = 'warning';
    } else {
      $status = 'critical';
    }
    
    $plateCosting->setCostStatus($status);
    $plateCosting->setUpdatedAt(new \DateTime());
    
    $this->em->persist($plateCosting);
    $this->em->flush();
    
    return $plateCosting;
  }

  /**
   * Calculate the target menu price needed to achieve a desired food cost percentage
   */
  public function calculateTargetPrice(
    Recipe $recipe,
    float $targetFoodCostPct
  ): float
  {
    $recipeCost = $this->getRecipeCost($recipe, 1.0);
        
    if ($targetFoodCostPct <= 0 || $targetFoodCostPct >= 100) {
      throw new \InvalidArgumentException('Target food cost percentage must be between 0 and 100');
    }
        
    return $recipeCost / ($targetFoodCostPct / 100);
  }

  /**
   * Get menu cost analysis with filtering
   * 
   * @param array{status?: string, min_cost?: float, max_cost?: float} $filters
   * @return array<array{
   *   recipe: Recipe,
   *   current_cost: float,
   *   current_price: float,
   *   food_cost_pct: float,
   *   status: string,
   *   variance_from_target: float
   * }>
   */
  public function getMenuCostAnalysis(array $filters = []): array
  {
    $qb = $this->plateCostingRepo->createQueryBuilder('pc')
            ->join('pc.recipe', 'r')
            ->orderBy('pc.current_food_cost_pct', 'DESC');
        
    if (isset($filters['status'])) {
      $qb->andWhere('pc.cost_status = :status')
         ->setParameter('status', $filters['status']);
    }
        
    if (isset($filters['min_cost'])) {
      $qb->andWhere('pc.current_cost >= :min_cost')
         ->setParameter('min_cost', $filters['min_cost']);
    }
        
    if (isset($filters['max_cost'])) {
      $qb->andWhere('pc.current_cost <= :max_cost')
         ->setParameter('max_cost', $filters['max_cost']);
    }

    $plateItems = $qb->getQuery()->getResult();
        
    $analysis = [];
    foreach ($plateItems as $plate) {
      $targetPct = $plate->getTargetFoodCostPct() ? (float)$plate->getTargetFoodCostPct() : 30.0;
      $currentPct = (float)$plate->getCurrentFoodCostPct();
            
      $analysis[] = [
        'recipe' => $plate->getRecipe(),
        'current_cost' => (float)$plate->getCurrentCost(),
        'current_price' => (float)$plate->getCurrentPrice(),
        'food_cost_pct' => $currentPct,
        'status' => $plate->getCostStatus(),
        'variance_from_target' => $currentPct - $targetPct,
      ];
    }
        
    return $analysis;
  }

  /**
   * Find all plates where food cost percentage exceeds threshold
   * 
   * @return array<PlateCosting>
   */
  public function flagOutOfRangePlates(float $threshold = 35.0): array
  {
    return $this->plateCostingRepo->createQueryBuilder('pc')
            ->where('pc.current_food_cost_pct > :threshold')
            ->andWhere('pc.alert_enabled = :enabled')
            ->setParameter('threshold', $threshold)
            ->setParameter('enabled', true)
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->getQuery()
            ->getResult();
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
  // PRICE VARIANCE & ANALYSIS
  // ============================================

  /**
   * Record a price point for historical tracking
   */
  public function recordPricePoint(
    Vendor $vendor,
    StockTarget $stockTarget,
    float $price,
    \DateTimeInterface $date,
    string $source = 'manual',
    ?int $sourceId = null,
    ?float $quantityPurchased = null
  ): void
  {
    $priceHistory = new PriceHistory();
    $priceHistory->setVendor($vendor);
    $priceHistory->setStockTarget($stockTarget);
    $priceHistory->setUnitPrice((string)$price);
    $priceHistory->setUnitOfMeasure($stockTarget->getBaseUnit());
    $priceHistory->setEffectiveDate($date);
    $priceHistory->setSourceType($source);
    $priceHistory->setSourceId($sourceId);
    $priceHistory->setQuantityPurchased($quantityPurchased ? (string)$quantityPurchased : null);
    $priceHistory->setRecordedAt(new \DateTime());
    
    $this->em->persist($priceHistory);
    $this->em->flush();
  }
  
  /**
   * Track cost changes over time for an item
   * 
   * @return array<array{date: \DateTimeInterface, unit_cost: float}>
   */
  public function getPriceHistory(
    StockTarget $target,
    ?Vendor $vendor = null,
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array
  {
     $qb = $this->priceHistoryRepo->createQueryBuilder('ph')
            ->where('ph.stock_target = :target')
            ->andWhere('ph.effective_date >= :from')
            ->andWhere('ph.effective_date <= :to')
            ->setParameter('target', $target)
            ->setParameter('from', $from)
            ->setParameter('to', $to)       
            ->orderBy('ph.effective_date', 'DESC');

     if ($vendor) {
       $qb->andWhere('ph.vendor = :vendor')
          ->setParameter('vendor', $vendor);
     }
     
     $records = $qb->getQuery()->getResult();
     
     $history = [];
     foreach ($records as $record) {
       $history[] = [
         'date' => $record->getEffectiveDate(),
         'price' => (float)$record->getUnitPrice(),
         'quantity' => $record->getQuantityPurchased() ? (float)$record->getQuantityPurchased() : null,
         'source' => $record->getSourceType(),
         'vendor' => $record->getVendor()->getName(),
       ];
     }
        
     return $history;
  }

  /**
   * Get average price over a time period
   */
  public function getAveragePrice(
    StockTarget $stockTarget,
    ?Vendor $vendor = null,
    \DateTimeInterface $from,
    \DateTimeInterface $to,
  ): float
  {
    $history = $this->getPriceHistory($stockTarget, $vendor, $from, $to);
        
    if (empty($history)) {
      return 0.0;
    }
    
    $total = array_sum(array_column($history, 'price'));
    return $total / count($history);
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
   * Calculate price variance for a current price against historical data.
   * 
   * @return array{
   *   current_price: float,
   *   avg_price: float,
   *   variance: float,
   *   variance_pct: float,
   *   min_price: float,
   *   max_price: float,
   *   price_trend: string~
   */
  public function getPriceVariance(
    StockTarget $stockTarget,
    float $currentPrice,
    ?Vendor $vendor = null,
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array
  {
    $history = $this->getPriceHistory($stockTarget, $vendor, $from, $to);
    
    if (empty($history)) {
      return [
        'current_price' => $currentPrice,
        'avg_price' => 0.0,
        'variance' => 0.0,
        'variance_pct' => 0.0,
        'min_price' => $currentPrice,
        'max_price' => $currentPrice,
        'price_trend' => 'unknown',
      ];
    }
    
    $prices = array_column($history, 'price');
    $avgPrice = array_sum($prices) / count($prices);
    $variance = $currentPrice - $avgPrice;
    $variancePct = $avgPrice > 0 ? ($variance / $avgPrice) * 100 : 0.0;
    
    // Determine trend (compare first half to second half of period)
    $midpoint = (int)(count($prices) / 2);
    $recentAvg = array_sum(array_slice($prices, 0, $midpoint)) / max(1, $midpoint);
    $olderAvg = array_sum(array_slice($prices, $midpoint)) / max(1, count($prices) - $midpoint);
    
    $trend = 'stable';
    if ($recentAvg > $olderAvg * 1.05) {
      $trend = 'increasing';
    } elseif ($recentAvg < $olderAvg * 0.95) {
      $trend = 'decreasing';
    }

    return [
      'current_price' => $currentPrice,
      'avg_price' => $avgPrice,
      'variance' => $variance,
      'variance_pct' => $variancePct,
      'min_price' => min($prices),
      'max_price' => max($prices),
      'price_trend' => $trend,
    ];
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
    // TODO: Make this configurable
    return 'weighted_average';
  }  
}
