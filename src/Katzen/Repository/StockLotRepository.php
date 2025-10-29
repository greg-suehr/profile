<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLot;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\Vendor;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLot>
 */
class StockLotRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLot::class);
  }

  public function save(StockLot $lot, bool $flush = true): void
  {
    $this->getEntityManager()->persist($lot);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(StockLot $lot, bool $flush = true): void
  {
    $this->getEntityManager()->remove($lot);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  // ============================================
  // COSTING QUERIES
  // ============================================

  /**
   * Find lots for a stock target ordered for FIFO, LIFO, or FEFO costing
   * 
   * @param StockTarget $target The stock item to query
   * @param 'FIFO'|'LIFO'|'FEFO' $method Costing method
   * @param StockLocation|null $location Optional location filter
   * @param bool $onlyAvailable Only return lots with available quantity (current_qty > reserved_qty)
   * @return StockLot[] Ordered array of lots
   * 
   * @example
   *   // Get lots for FIFO costing (oldest first)
   *   $lots = $repo->findLotsForCosting($target, 'FIFO');
   *   
   *   // Get lots for FEFO (earliest expiration first)
   *   $lots = $repo->findLotsForCosting($target, 'FEFO', onlyAvailable: true);
   */
  public function findLotsForCosting(
    StockTarget $target,
    string $method = 'FIFO',
    ?StockLocation $location = null,
    bool $onlyAvailable = true
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.stock_target = :target')
        ->setParameter('target', $target);

    // Filter by location if specified
    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->andWhere('lb.qty > 0')
         ->setParameter('location', $location);
    }

    // Only include lots with available quantity
    if ($onlyAvailable) {
      if ($location === null) {
        // No location filter: use lot-level current_qty
        $qb->andWhere('l.current_qty > l.reserved_qty');
      } else {
        // With location filter: already filtered by lb.qty > 0
        $qb->andWhere('lb.qty > lb.reserved_qty');
      }
    } else {
      // Include all lots, even depleted ones
      if ($location === null) {
        $qb->andWhere('l.current_qty > 0');
      }
    }

    // Order based on costing method
    switch ($method) {
      case 'LIFO':
        $qb->orderBy('l.received_date', 'DESC')
           ->addOrderBy('l.id', 'DESC');
        break;
      case 'FEFO':
        $qb->orderBy('l.expiration_date', 'ASC')
           ->addOrderBy('l.received_date', 'ASC')
           ->addOrderBy('l.id', 'ASC');
        break;
      case 'FIFO':
      default:
        $qb->orderBy('l.received_date', 'ASC')
           ->addOrderBy('l.id', 'ASC');
        break;
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get the weighted average unit cost for a stock target
   * 
   * Calculates: (sum of lot_value) / (sum of current_qty) where lot_value = current_qty * unit_cost
   * 
   * @param StockTarget $target
   * @param StockLocation|null $location Optional location filter
   * @return float Weighted average cost per unit, or 0.0 if no lots exist
   */
  public function getWeightedAverageCost(
    StockTarget $target,
    ?StockLocation $location = null
  ): float
  {
    $qb = $this->createQueryBuilder('l')
        ->select('SUM(l.current_qty * l.unit_cost) as totalValue')
        ->addSelect('SUM(l.current_qty) as totalQty')
        ->where('l.stock_target = :target')
        ->andWhere('l.current_qty > 0')
        ->setParameter('target', $target);

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->setParameter('location', $location);
    }

    $result = $qb->getQuery()->getSingleResult();

    $totalValue = (float)($result['totalValue'] ?? 0.0);
    $totalQty = (float)($result['totalQty'] ?? 0.0);

    return $totalQty > 0 ? ($totalValue / $totalQty) : 0.0;
  }

  // ============================================
  // EXPIRATION & QUALITY MANAGEMENT
  // ============================================

  /**
   * Find lots expiring within a date range
   * 
   * @param \DateTimeInterface $from Start date (inclusive)
   * @param \DateTimeInterface $to End date (inclusive)
   * @param bool $onlyAvailable Only return lots with current_qty > 0
   * @param StockTarget|null $target Optional filter by stock target
   * @param StockLocation|null $location Optional location filter
   * @return StockLot[] Lots ordered by expiration date (earliest first)
   */
  public function findExpiringLots(
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    bool $onlyAvailable = true,
    ?StockTarget $target = null,
    ?StockLocation $location = null
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.expiration_date >= :from')
        ->andWhere('l.expiration_date <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->orderBy('l.expiration_date', 'ASC')
        ->addOrderBy('l.stock_target', 'ASC');

    if ($onlyAvailable) {
      $qb->andWhere('l.current_qty > 0');
    }

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->andWhere('lb.qty > 0')
         ->setParameter('location', $location);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find lots that are already expired
   * 
   * @param \DateTimeInterface|null $asOf Date to check expiration against (defaults to now)
   * @param bool $onlyAvailable Only return lots with current_qty > 0
   * @param StockLocation|null $location Optional location filter
   * @return StockLot[] Expired lots ordered by expiration date
   */
  public function findExpiredLots(
    ?\DateTimeInterface $asOf = null,
    bool $onlyAvailable = true,
    ?StockLocation $location = null
  ): array
  {
    $asOf = $asOf ?? new \DateTime();

    $qb = $this->createQueryBuilder('l')
        ->where('l.expiration_date < :asOf')
        ->setParameter('asOf', $asOf)
        ->orderBy('l.expiration_date', 'ASC');

    if ($onlyAvailable) {
      $qb->andWhere('l.current_qty > 0');
    }

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->andWhere('lb.qty > 0')
         ->setParameter('location', $location);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find lots approaching expiration (within X days)
   * 
   * @param int $daysThreshold Number of days before expiration to flag
   * @param bool $onlyAvailable Only return lots with current_qty > 0
   * @param StockTarget|null $target Optional filter by stock target
   * @param StockLocation|null $location Optional location filter
   * @return StockLot[] Lots expiring soon
   */
  public function findLotsExpiringSoon(
    int $daysThreshold = 30,
    bool $onlyAvailable = true,
    ?StockTarget $target = null,
    ?StockLocation $location = null
  ): array
  {
    $now = new \DateTime();
    $threshold = (clone $now)->modify("+{$daysThreshold} days");

    return $this->findExpiringLots(
      $now,
      $threshold,
      $onlyAvailable,
      $target,
      $location
    );
  }

  // ============================================
  // LOT TRACEABILITY & LOOKUP
  // ============================================

  /**
   * Find a lot by its lot number
   * 
   * @param string $lotNumber
   * @return StockLot|null
   */
  public function findByLotNumber(string $lotNumber): ?StockLot
  {
    return $this->findOneBy(['lot_number' => $lotNumber]);
  }

  /**
   * Find all lots for a specific stock target
   * 
   * @param StockTarget $target
   * @param bool $onlyActive Only return lots with current_qty > 0
   * @param string $orderBy 'received_date'|'expiration_date'|'unit_cost'
   * @param string $orderDir 'ASC'|'DESC'
   * @return StockLot[]
   */
  public function findByStockTarget(
    StockTarget $target,
    bool $onlyActive = false,
    string $orderBy = 'received_date',
    string $orderDir = 'DESC'
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.stock_target = :target')
        ->setParameter('target', $target);

    if ($onlyActive) {
      $qb->andWhere('l.current_qty > 0');
    }

    $validOrderFields = ['received_date', 'expiration_date', 'unit_cost', 'current_qty'];
    $orderField = in_array($orderBy, $validOrderFields) ? $orderBy : 'received_date';
    
    $qb->orderBy("l.{$orderField}", strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC')
       ->addOrderBy('l.id', 'ASC');

    return $qb->getQuery()->getResult();
  }

  /**
   * Find lots by vendor
   * 
   * @param Vendor $vendor
   * @param bool $onlyActive Only return lots with current_qty > 0
   * @param int|null $limit Maximum results
   * @return StockLot[]
   */
  public function findByVendor(
    Vendor $vendor,
    bool $onlyActive = true,
    ?int $limit = null
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.vendor = :vendor')
        ->setParameter('vendor', $vendor)
        ->orderBy('l.received_date', 'DESC');

    if ($onlyActive) {
      $qb->andWhere('l.current_qty > 0');
    }

    if ($limit !== null) {
      $qb->setMaxResults($limit);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find lots received within a date range
   * 
   * @param \DateTimeInterface $from
   * @param \DateTimeInterface $to
   * @param StockTarget|null $target Optional filter by stock target
   * @param Vendor|null $vendor Optional filter by vendor
   * @return StockLot[]
   */
  public function findReceivedBetween(
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    ?StockTarget $target = null,
    ?Vendor $vendor = null
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.received_date >= :from')
        ->andWhere('l.received_date <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->orderBy('l.received_date', 'DESC');

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    if ($vendor !== null) {
      $qb->andWhere('l.vendor = :vendor')
         ->setParameter('vendor', $vendor);
    }

    return $qb->getQuery()->getResult();
  }

  // ============================================
  // INVENTORY VALUATION & ANALYSIS
  // ============================================

  /**
   * Calculate total inventory value for a stock target
   * 
   * @param StockTarget $target
   * @param StockLocation|null $location Optional location filter
   * @return float Total value (sum of current_qty * unit_cost for all lots)
   */
  public function getTotalValue(
    StockTarget $target,
    ?StockLocation $location = null
  ): float
  {
    $qb = $this->createQueryBuilder('l')
        ->select('SUM(l.current_qty * l.unit_cost) as totalValue')
        ->where('l.stock_target = :target')
        ->andWhere('l.current_qty > 0')
        ->setParameter('target', $target);

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->setParameter('location', $location);
    }

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  /**
   * Get total available quantity for a stock target across all lots
   * 
   * @param StockTarget $target
   * @param StockLocation|null $location Optional location filter
   * @return float Total available quantity (current_qty - reserved_qty)
   */
  public function getTotalAvailableQuantity(
    StockTarget $target,
    ?StockLocation $location = null
  ): float
  {
    $qb = $this->createQueryBuilder('l')
        ->select('SUM(l.current_qty - l.reserved_qty) as availableQty')
        ->where('l.stock_target = :target')
        ->andWhere('l.current_qty > l.reserved_qty')
        ->setParameter('target', $target);

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->setParameter('location', $location);
    }

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  /**
   * Get inventory age analysis for a stock target
   * 
   * Returns statistics about how old the inventory is based on received_date
   * 
   * @param StockTarget $target
   * @return array{
   *   total_qty: float,
   *   total_value: float,
   *   oldest_lot_date: \DateTimeInterface|null,
   *   newest_lot_date: \DateTimeInterface|null,
   *   average_age_days: float|null
   * }
   */
  public function getInventoryAgeAnalysis(StockTarget $target): array
  {
    $qb = $this->createQueryBuilder('l')
        ->select('SUM(l.current_qty) as totalQty')
        ->addSelect('SUM(l.current_qty * l.unit_cost) as totalValue')
        ->addSelect('MIN(l.received_date) as oldestDate')
        ->addSelect('MAX(l.received_date) as newestDate')
        ->where('l.stock_target = :target')
        ->andWhere('l.current_qty > 0')
        ->setParameter('target', $target);

    $result = $qb->getQuery()->getSingleResult();

    // Calculate weighted average age
    $lots = $this->findByStockTarget($target, onlyActive: true);
    $weightedAgeDays = null;
    
    if (!empty($lots) && $result['totalQty'] > 0) {
      $now = new \DateTime();
      $totalWeightedAge = 0.0;
      
      foreach ($lots as $lot) {
        $qty = (float)$lot->getCurrentQty();
        $age = $now->diff($lot->getReceivedDate())->days;
        $totalWeightedAge += $age * $qty;
      }
      
      $weightedAgeDays = $totalWeightedAge / (float)$result['totalQty'];
    }

    return [
      'total_qty' => (float)($result['totalQty'] ?? 0.0),
      'total_value' => (float)($result['totalValue'] ?? 0.0),
      'oldest_lot_date' => $result['oldestDate'],
      'newest_lot_date' => $result['newestDate'],
      'average_age_days' => $weightedAgeDays,
    ];
  }

  /**
   * Get lots with low stock (where current_qty is below a threshold percentage of initial_qty)
   * 
   * @param float $thresholdPercent Percentage of initial quantity (e.g., 20.0 for 20%)
   * @param StockTarget|null $target Optional filter by stock target
   * @param StockLocation|null $location Optional location filter
   * @return StockLot[] Lots with low remaining quantity
   */
  public function findLowStockLots(
    float $thresholdPercent = 20.0,
    ?StockTarget $target = null,
    ?StockLocation $location = null
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->where('l.current_qty > 0')
        ->andWhere('(l.current_qty / l.initial_qty * 100) <= :threshold')
        ->setParameter('threshold', $thresholdPercent)
        ->orderBy('(l.current_qty / l.initial_qty)', 'ASC');

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->andWhere('lb.qty > 0')
         ->setParameter('location', $location);
    }

    return $qb->getQuery()->getResult();
  }

  // ============================================
  // VENDOR PERFORMANCE ANALYSIS
  // ============================================

  /**
   * Get cost variance for a stock target across vendors over time
   * 
   * Shows how unit costs have varied by vendor
   * 
   * @param StockTarget $target
   * @param \DateTimeInterface|null $from Optional start date
   * @param \DateTimeInterface|null $to Optional end date
   * @return array<array{
   *   vendor_id: int|null,
   *   vendor_name: string|null,
   *   avg_unit_cost: float,
   *   min_unit_cost: float,
   *   max_unit_cost: float,
   *   total_qty_received: float,
   *   receipt_count: int
   * }> Statistics grouped by vendor
   */
  public function getVendorCostComparison(
    StockTarget $target,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null
  ): array
  {
    $qb = $this->createQueryBuilder('l')
        ->select('IDENTITY(l.vendor) as vendor_id')
        ->addSelect('v.name as vendor_name')
        ->addSelect('AVG(l.unit_cost) as avg_unit_cost')
        ->addSelect('MIN(l.unit_cost) as min_unit_cost')
        ->addSelect('MAX(l.unit_cost) as max_unit_cost')
        ->addSelect('SUM(l.initial_qty) as total_qty_received')
        ->addSelect('COUNT(l.id) as receipt_count')
        ->leftJoin('l.vendor', 'v')
        ->where('l.stock_target = :target')
        ->setParameter('target', $target)
        ->groupBy('l.vendor, v.name')
        ->orderBy('avg_unit_cost', 'ASC');

    if ($from !== null) {
      $qb->andWhere('l.received_date >= :from')
         ->setParameter('from', $from);
    }

    if ($to !== null) {
      $qb->andWhere('l.received_date <= :to')
         ->setParameter('to', $to);
    }

    $results = $qb->getQuery()->getResult();

    // Cast numeric values to proper types
    return array_map(function($row) {
      return [
        'vendor_id' => $row['vendor_id'] ? (int)$row['vendor_id'] : null,
        'vendor_name' => $row['vendor_name'],
        'avg_unit_cost' => (float)$row['avg_unit_cost'],
        'min_unit_cost' => (float)$row['min_unit_cost'],
        'max_unit_cost' => (float)$row['max_unit_cost'],
        'total_qty_received' => (float)$row['total_qty_received'],
        'receipt_count' => (int)$row['receipt_count'],
      ];
    }, $results);
  }

  // ============================================
  // AGGREGATION & REPORTING
  // ============================================

  /**
   * Get complete inventory summary by stock target
   * 
   * @param StockLocation|null $location Optional location filter
   * @return array<array{
   *   stock_target_id: int,
   *   stock_target_name: string,
   *   lot_count: int,
   *   total_qty: float,
   *   total_reserved_qty: float,
   *   total_available_qty: float,
   *   total_value: float,
   *   weighted_avg_cost: float,
   *   oldest_lot_date: \DateTimeInterface|null,
   *   lots_expiring_soon: int (count of lots expiring in next 30 days)
   * }>
   */
  public function getInventorySummary(?StockLocation $location = null): array
  {
    $now = new \DateTime();
    $soonThreshold = (clone $now)->modify('+30 days');

    $qb = $this->createQueryBuilder('l')
        ->select('IDENTITY(l.stock_target) as stock_target_id')
        ->addSelect('st.name as stock_target_name')
        ->addSelect('COUNT(l.id) as lot_count')
        ->addSelect('SUM(l.current_qty) as total_qty')
        ->addSelect('SUM(l.reserved_qty) as total_reserved_qty')
        ->addSelect('SUM(l.current_qty - l.reserved_qty) as total_available_qty')
        ->addSelect('SUM(l.current_qty * l.unit_cost) as total_value')
        ->addSelect('SUM(l.current_qty * l.unit_cost) / NULLIF(SUM(l.current_qty), 0) as weighted_avg_cost')
        ->addSelect('MIN(l.received_date) as oldest_lot_date')
        ->addSelect('SUM(CASE WHEN l.expiration_date BETWEEN :now AND :soonThreshold AND l.current_qty > 0 THEN 1 ELSE 0 END) as lots_expiring_soon')
        ->join('l.stock_target', 'st')
        ->where('l.current_qty > 0')
        ->setParameter('now', $now)
        ->setParameter('soonThreshold', $soonThreshold)
        ->groupBy('l.stock_target, st.name')
        ->orderBy('total_value', 'DESC');

    if ($location !== null) {
      $qb->join('l.locationBalances', 'lb')
         ->andWhere('lb.location = :location')
         ->andWhere('lb.qty > 0')
         ->setParameter('location', $location);
    }

    $results = $qb->getQuery()->getResult();

    // Cast numeric values to proper types
    return array_map(function($row) {
      return [
        'stock_target_id' => (int)$row['stock_target_id'],
        'stock_target_name' => $row['stock_target_name'],
        'lot_count' => (int)$row['lot_count'],
        'total_qty' => (float)$row['total_qty'],
        'total_reserved_qty' => (float)$row['total_reserved_qty'],
        'total_available_qty' => (float)($row['total_available_qty'] ?? 0.0),
        'total_value' => (float)$row['total_value'],
        'weighted_avg_cost' => (float)($row['weighted_avg_cost'] ?? 0.0),
        'oldest_lot_date' => $row['oldest_lot_date'],
        'lots_expiring_soon' => (int)$row['lots_expiring_soon'],
      ];
    }, $results);
  }
}
