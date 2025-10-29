<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockLot;
use App\Katzen\Entity\StockLotLocationBalance;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\StockTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLotLocationBalance>
 */
class StockLotLocationBalanceRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockLotLocationBalance::class);
  }

  public function save(StockLotLocationBalance $balance, bool $flush = true): void
  {
    $this->getEntityManager()->persist($balance);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(StockLotLocationBalance $balance, bool $flush = true): void
  {
    $this->getEntityManager()->remove($balance);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  // ============================================
  // LOCATION-AWARE COSTING QUERIES
  // ============================================

  /**
   * Find location balances for costing (FIFO/LIFO/FEFO)
   * 
   * Returns balances ordered for the specified costing method, filtered by location
   * 
   * @param StockTarget $target The stock item to query
   * @param StockLocation $location The location to query
   * @param 'FIFO'|'LIFO'|'FEFO' $method Costing method
   * @param bool $onlyAvailable Only return balances with available quantity
   * @return StockLotLocationBalance[] Ordered array of balances
   * 
   * @example
   *   // Get balances for FIFO costing at a specific location
   *   $balances = $repo->findBalancesForCosting($target, $location, 'FIFO');
   */
  public function findBalancesForCosting(
    StockTarget $target,
    StockLocation $location,
    string $method = 'FIFO',
    bool $onlyAvailable = true
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->setParameter('target', $target)
        ->setParameter('location', $location);

    if ($onlyAvailable) {
      $qb->andWhere('b.qty > b.reserved_qty');
    } else {
      $qb->andWhere('b.qty > 0');
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
   * Get weighted average unit cost at a specific location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @return float Weighted average cost per unit, or 0.0 if no balances exist
   */
  public function getWeightedAverageCost(
    StockTarget $target,
    StockLocation $location
  ): float
  {
    $qb = $this->createQueryBuilder('b')
        ->select('SUM(b.qty * l.unit_cost) as totalValue')
        ->addSelect('SUM(b.qty) as totalQty')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->andWhere('b.qty > 0')
        ->setParameter('target', $target)
        ->setParameter('location', $location);

    $result = $qb->getQuery()->getSingleResult();

    $totalValue = (float)($result['totalValue'] ?? 0.0);
    $totalQty = (float)($result['totalQty'] ?? 0.0);

    return $totalQty > 0 ? ($totalValue / $totalQty) : 0.0;
  }

  // ============================================
  // AVAILABILITY & QUANTITY QUERIES
  // ============================================

  /**
   * Get total available quantity at a location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @return float Total available quantity (qty - reserved_qty)
   */
  public function getTotalAvailableQuantity(
    StockTarget $target,
    StockLocation $location
  ): float
  {
    $qb = $this->createQueryBuilder('b')
        ->select('SUM(b.qty - b.reserved_qty) as availableQty')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->andWhere('b.qty > b.reserved_qty')
        ->setParameter('target', $target)
        ->setParameter('location', $location);

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  /**
   * Get total quantity (including reserved) at a location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @return float Total quantity
   */
  public function getTotalQuantity(
    StockTarget $target,
    StockLocation $location
  ): float
  {
    $qb = $this->createQueryBuilder('b')
        ->select('SUM(b.qty) as totalQty')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->andWhere('b.qty > 0')
        ->setParameter('target', $target)
        ->setParameter('location', $location);

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  /**
   * Get total reserved quantity at a location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @return float Total reserved quantity
   */
  public function getTotalReservedQuantity(
    StockTarget $target,
    StockLocation $location
  ): float
  {
    $qb = $this->createQueryBuilder('b')
        ->select('SUM(b.reserved_qty) as reservedQty')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->andWhere('b.reserved_qty > 0')
        ->setParameter('target', $target)
        ->setParameter('location', $location);

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  /**
   * Check if sufficient quantity is available at a location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @param float $requiredQty
   * @return bool True if sufficient quantity available
   */
  public function hasAvailableQuantity(
    StockTarget $target,
    StockLocation $location,
    float $requiredQty
  ): bool
  {
    $available = $this->getTotalAvailableQuantity($target, $location);
    return $available >= $requiredQty;
  }

  // ============================================
  // LOCATION-SPECIFIC LOOKUPS
  // ============================================

  /**
   * Find all balances for a stock target at a specific location
   * 
   * @param StockTarget $target
   * @param StockLocation $location
   * @param bool $onlyAvailable Only return balances with qty > 0
   * @return StockLotLocationBalance[]
   */
  public function findByStockTargetAndLocation(
    StockTarget $target,
    StockLocation $location,
    bool $onlyAvailable = true
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.location = :location')
        ->setParameter('target', $target)
        ->setParameter('location', $location)
        ->orderBy('l.received_date', 'ASC');

    if ($onlyAvailable) {
      $qb->andWhere('b.qty > 0');
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find balance for a specific lot at a specific location
   * 
   * @param StockLot $lot
   * @param StockLocation $location
   * @return StockLotLocationBalance|null
   */
  public function findByLotAndLocation(
    StockLot $lot,
    StockLocation $location
  ): ?StockLotLocationBalance
  {
    return $this->findOneBy([
      'stock_lot' => $lot,
      'location' => $location,
    ]);
  }

  /**
   * Find all balances for a specific lot across all locations
   * 
   * @param StockLot $lot
   * @param bool $onlyAvailable Only return balances with qty > 0
   * @return StockLotLocationBalance[]
   */
  public function findByLot(
    StockLot $lot,
    bool $onlyAvailable = true
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->where('b.stock_lot = :lot')
        ->setParameter('lot', $lot)
        ->orderBy('b.qty', 'DESC');

    if ($onlyAvailable) {
      $qb->andWhere('b.qty > 0');
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find all balances at a specific location
   * 
   * @param StockLocation $location
   * @param bool $onlyAvailable Only return balances with qty > 0
   * @param int|null $limit Maximum results
   * @return StockLotLocationBalance[]
   */
  public function findByLocation(
    StockLocation $location,
    bool $onlyAvailable = true,
    ?int $limit = null
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->where('b.location = :location')
        ->setParameter('location', $location)
        ->orderBy('b.qty', 'DESC');

    if ($onlyAvailable) {
      $qb->andWhere('b.qty > 0');
    }

    if ($limit !== null) {
      $qb->setMaxResults($limit);
    }

    return $qb->getQuery()->getResult();
  }

  // ============================================
  // CROSS-LOCATION ANALYSIS
  // ============================================

  /**
   * Get quantity distribution across all locations for a stock target
   * 
   * @param StockTarget $target
   * @return array<array{
   *   location_id: int,
   *   location_name: string,
   *   total_qty: float,
   *   available_qty: float,
   *   reserved_qty: float,
   *   lot_count: int
   * }>
   */
  public function getLocationDistribution(StockTarget $target): array
  {
    $qb = $this->createQueryBuilder('b')
        ->select('IDENTITY(b.location) as location_id')
        ->addSelect('loc.name as location_name')
        ->addSelect('SUM(b.qty) as total_qty')
        ->addSelect('SUM(b.qty - b.reserved_qty) as available_qty')
        ->addSelect('SUM(b.reserved_qty) as reserved_qty')
        ->addSelect('COUNT(DISTINCT b.stock_lot) as lot_count')
        ->join('b.stock_lot', 'l')
        ->join('b.location', 'loc')
        ->where('l.stock_target = :target')
        ->andWhere('b.qty > 0')
        ->setParameter('target', $target)
        ->groupBy('b.location, loc.name')
        ->orderBy('total_qty', 'DESC');

    $results = $qb->getQuery()->getResult();

    return array_map(function($row) {
      return [
        'location_id' => (int)$row['location_id'],
        'location_name' => $row['location_name'],
        'total_qty' => (float)$row['total_qty'],
        'available_qty' => (float)($row['available_qty'] ?? 0.0),
        'reserved_qty' => (float)$row['reserved_qty'],
        'lot_count' => (int)$row['lot_count'],
      ];
    }, $results);
  }

  /**
   * Find locations where a stock target is available
   * 
   * @param StockTarget $target
   * @param float|null $minQty Optional minimum quantity threshold
   * @return StockLocation[] Locations with available stock
   */
  public function findLocationsWithStock(
    StockTarget $target,
    ?float $minQty = null
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->select('loc')
        ->join('b.location', 'loc')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.qty > b.reserved_qty')
        ->setParameter('target', $target)
        ->groupBy('loc.id')
        ->orderBy('loc.name', 'ASC');

    if ($minQty !== null) {
      $qb->having('SUM(b.qty - b.reserved_qty) >= :minQty')
         ->setParameter('minQty', $minQty);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get total inventory value at a location
   * 
   * @param StockLocation $location
   * @param StockTarget|null $target Optional filter by stock target
   * @return float Total value (sum of qty * unit_cost)
   */
  public function getTotalValueAtLocation(
    StockLocation $location,
    ?StockTarget $target = null
  ): float
  {
    $qb = $this->createQueryBuilder('b')
        ->select('SUM(b.qty * l.unit_cost) as totalValue')
        ->join('b.stock_lot', 'l')
        ->where('b.location = :location')
        ->andWhere('b.qty > 0')
        ->setParameter('location', $location);

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    $result = $qb->getQuery()->getSingleScalarResult();

    return (float)($result ?? 0.0);
  }

  // ============================================
  // EXPIRATION TRACKING (LOCATION-AWARE)
  // ============================================

  /**
   * Find balances at a location with expiring lots
   * 
   * @param StockLocation $location
   * @param \DateTimeInterface $from Start date (inclusive)
   * @param \DateTimeInterface $to End date (inclusive)
   * @param StockTarget|null $target Optional filter by stock target
   * @return StockLotLocationBalance[] Balances with expiring lots
   */
  public function findExpiringBalances(
    StockLocation $location,
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    ?StockTarget $target = null
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->join('b.stock_lot', 'l')
        ->where('b.location = :location')
        ->andWhere('b.qty > 0')
        ->andWhere('l.expiration_date >= :from')
        ->andWhere('l.expiration_date <= :to')
        ->setParameter('location', $location)
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->orderBy('l.expiration_date', 'ASC');

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find expired balances at a location
   * 
   * @param StockLocation $location
   * @param \DateTimeInterface|null $asOf Date to check expiration against (defaults to now)
   * @param StockTarget|null $target Optional filter by stock target
   * @return StockLotLocationBalance[] Expired balances with qty > 0
   */
  public function findExpiredBalances(
    StockLocation $location,
    ?\DateTimeInterface $asOf = null,
    ?StockTarget $target = null
  ): array
  {
    $asOf = $asOf ?? new \DateTime();

    $qb = $this->createQueryBuilder('b')
        ->join('b.stock_lot', 'l')
        ->where('b.location = :location')
        ->andWhere('b.qty > 0')
        ->andWhere('l.expiration_date < :asOf')
        ->setParameter('location', $location)
        ->setParameter('asOf', $asOf)
        ->orderBy('l.expiration_date', 'ASC');

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    return $qb->getQuery()->getResult();
  }

  // ============================================
  // TRANSFER & ALLOCATION SUPPORT
  // ============================================

  /**
   * Find the best source location for transferring stock
   * 
   * Returns locations with available stock, ordered by quantity (most stock first)
   * 
   * @param StockTarget $target
   * @param float $requiredQty
   * @param StockLocation|null $excludeLocation Location to exclude (e.g., destination)
   * @return array<array{
   *   location: StockLocation,
   *   available_qty: float,
   *   lot_count: int
   * }>
   */
  public function findSourceLocationsForTransfer(
    StockTarget $target,
    float $requiredQty,
    ?StockLocation $excludeLocation = null
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->select('loc as location')
        ->addSelect('SUM(b.qty - b.reserved_qty) as available_qty')
        ->addSelect('COUNT(DISTINCT b.stock_lot) as lot_count')
        ->join('b.location', 'loc')
        ->join('b.stock_lot', 'l')
        ->where('l.stock_target = :target')
        ->andWhere('b.qty > b.reserved_qty')
        ->setParameter('target', $target)
        ->groupBy('loc.id')
        ->having('available_qty >= :requiredQty')
        ->setParameter('requiredQty', $requiredQty)
        ->orderBy('available_qty', 'DESC');

    if ($excludeLocation !== null) {
      $qb->andWhere('b.location != :excludeLoc')
         ->setParameter('excludeLoc', $excludeLocation);
    }

    $results = $qb->getQuery()->getResult();

    return array_map(function($row) {
      return [
        'location' => $row['location'],
        'available_qty' => (float)$row['available_qty'],
        'lot_count' => (int)$row['lot_count'],
      ];
    }, $results);
  }

  /**
   * Find balances with reserved quantity
   * 
   * Useful for tracking pending allocations and reservations
   * 
   * @param StockLocation|null $location Optional location filter
   * @param StockTarget|null $target Optional stock target filter
   * @return StockLotLocationBalance[]
   */
  public function findWithReservations(
    ?StockLocation $location = null,
    ?StockTarget $target = null
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->join('b.stock_lot', 'l')
        ->where('b.reserved_qty > 0')
        ->orderBy('b.reserved_qty', 'DESC');

    if ($location !== null) {
      $qb->andWhere('b.location = :location')
         ->setParameter('location', $location);
    }

    if ($target !== null) {
      $qb->andWhere('l.stock_target = :target')
         ->setParameter('target', $target);
    }

    return $qb->getQuery()->getResult();
  }

  // ============================================
  // BATCH OPERATIONS
  // ============================================

  /**
   * Bulk update reserved quantities for multiple balances
   * 
   * Useful for allocation/reservation operations
   * 
   * @param array<int, float> $reservations Map of balance_id => reserved_qty_delta
   * @return int Number of balances updated
   */
  public function bulkUpdateReservations(array $reservations): int
  {
    $em = $this->getEntityManager();
    $count = 0;

    foreach ($reservations as $balanceId => $delta) {
      $balance = $this->find($balanceId);
      
      if ($balance) {
        $currentReserved = (float)$balance->getReservedQty();
        $newReserved = max(0.0, $currentReserved + $delta);
        
        $balance->setReservedQty((string)$newReserved);
        $balance->setUpdatedAt(new \DateTime());
        
        $count++;
      }
    }

    $em->flush();

    return $count;
  }

  // ============================================
  // REPORTING & AGGREGATION
  // ============================================

  /**
   * Get comprehensive inventory summary for a location
   * 
   * @param StockLocation $location
   * @return array<array{
   *   stock_target_id: int,
   *   stock_target_name: string,
   *   lot_count: int,
   *   total_qty: float,
   *   available_qty: float,
   *   reserved_qty: float,
   *   total_value: float,
   *   weighted_avg_cost: float
   * }>
   */
  public function getInventorySummaryByLocation(StockLocation $location): array
  {
    $qb = $this->createQueryBuilder('b')
        ->select('IDENTITY(l.stock_target) as stock_target_id')
        ->addSelect('st.name as stock_target_name')
        ->addSelect('COUNT(DISTINCT b.stock_lot) as lot_count')
        ->addSelect('SUM(b.qty) as total_qty')
        ->addSelect('SUM(b.qty - b.reserved_qty) as available_qty')
        ->addSelect('SUM(b.reserved_qty) as reserved_qty')
        ->addSelect('SUM(b.qty * l.unit_cost) as total_value')
        ->addSelect('SUM(b.qty * l.unit_cost) / NULLIF(SUM(b.qty), 0) as weighted_avg_cost')
        ->join('b.stock_lot', 'l')
        ->join('l.stock_target', 'st')
        ->where('b.location = :location')
        ->andWhere('b.qty > 0')
        ->setParameter('location', $location)
        ->groupBy('l.stock_target, st.name')
        ->orderBy('total_value', 'DESC');

    $results = $qb->getQuery()->getResult();

    return array_map(function($row) {
      return [
        'stock_target_id' => (int)$row['stock_target_id'],
        'stock_target_name' => $row['stock_target_name'],
        'lot_count' => (int)$row['lot_count'],
        'total_qty' => (float)$row['total_qty'],
        'available_qty' => (float)($row['available_qty'] ?? 0.0),
        'reserved_qty' => (float)$row['reserved_qty'],
        'total_value' => (float)$row['total_value'],
        'weighted_avg_cost' => (float)($row['weighted_avg_cost'] ?? 0.0),
      ];
    }, $results);
  }

  /**
   * Get low stock items at a location
   * 
   * Compares available quantity against a threshold
   * 
   * @param StockLocation $location
   * @param float $thresholdQty Minimum quantity threshold
   * @return array<array{
   *   stock_target_id: int,
   *   stock_target_name: string,
   *   available_qty: float,
   *   reserved_qty: float,
   *   lot_count: int
   * }>
   */
  public function findLowStockAtLocation(
    StockLocation $location,
    float $thresholdQty = 10.0
  ): array
  {
    $qb = $this->createQueryBuilder('b')
        ->select('IDENTITY(l.stock_target) as stock_target_id')
        ->addSelect('st.name as stock_target_name')
        ->addSelect('SUM(b.qty - b.reserved_qty) as available_qty')
        ->addSelect('SUM(b.reserved_qty) as reserved_qty')
        ->addSelect('COUNT(DISTINCT b.stock_lot) as lot_count')
        ->join('b.stock_lot', 'l')
        ->join('l.stock_target', 'st')
        ->where('b.location = :location')
        ->andWhere('b.qty > 0')
        ->setParameter('location', $location)
        ->groupBy('l.stock_target, st.name')
        ->having('available_qty < :threshold')
        ->setParameter('threshold', $thresholdQty)
        ->orderBy('available_qty', 'ASC');

    $results = $qb->getQuery()->getResult();

    return array_map(function($row) {
      return [
        'stock_target_id' => (int)$row['stock_target_id'],
        'stock_target_name' => $row['stock_target_name'],
        'available_qty' => (float)($row['available_qty'] ?? 0.0),
        'reserved_qty' => (float)$row['reserved_qty'],
        'lot_count' => (int)$row['lot_count'],
      ];
    }, $results);
  }
}
