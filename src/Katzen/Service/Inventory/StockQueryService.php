<?php

namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\StockTarget;
use App\Katzen\ValueObject\LocationScope;
use Doctrine\DBAL\Connection;

/**
 * StockQueryService - Location-aware inventory queries
 * 
 * This service provides read-only inventory queries that respect location scoping.
 * All quantities are computed from stock_lot_location_balance, not stock_lot.current_qty.
 */
final class StockQueryService
{
  public function __construct(
    private Connection $connection,
  ) {}
  
  /**
   * Get current quantity for a stock target within a location scope
   * 
   * Returns:
   * [
   *   'total' => float,
   *   'reserved' => float,
   *   'available' => float,
   * ]
   */
  public function getCurrentQty(int $targetId, LocationScope $scope): array
  {
    $qb = $this->connection->createQueryBuilder();

    $qb->select('SUM(sllb.qty) as total', 'SUM(sllb.reserved_qty) as reserved')
            ->from('stock_lot', 'sl')
            ->innerJoin('sl', 'stock_lot_location_balance', 'sllb', 'sllb.stock_lot_id = sl.id')
            ->where('sl.stock_target_id = :targetId')
            ->setParameter('targetId', $targetId);
    
    if (!$scope->isAll()) {
      $qb->andWhere('sllb.location_id IN (:locationIds)')
               ->setParameter('locationIds', $scope->locationIds);
    }

    $result = $qb->getQuery()->getResult();
    
    $total = (float)($result['total'] ?? 0);
    $reserved = (float)($result['reserved'] ?? 0);
    
    return [
      'total' => $total,
      'reserved' => $reserved,
      'available' => $total - $reserved,
    ];
  }
  
  /**
   * Get per-location quantity breakdown for a stock target
   * 
   * Returns array of:
   * [
   *   ['location_id' => 1, 'location_name' => 'Kitchen', 'qty' => 12.50, 'reserved' => 2.00],
   *   ['location_id' => 2, 'location_name' => 'Bar', 'qty' => 4.00, 'reserved' => 0.00],
   * ]
   */
  public function getQtyByLocation(int $targetId, LocationScope $scope): array
  {
    $qb = $this->connection->createQueryBuilder();
        
    $qb->select(
      'sllb.location_id',
      'loc.name as location_name',
      'SUM(sllb.qty) as qty',
      'SUM(sllb.reserved_qty) as reserved'
    )
            ->from('stock_lot', 'sl')
            ->innerJoin('sl', 'stock_lot_location_balance', 'sllb', 'sllb.stock_lot_id = sl.id')
            ->innerJoin('sllb', 'stock_location', 'loc', 'loc.id = sllb.location_id')
            ->where('sl.stock_target_id = :targetId')
            ->groupBy('sllb.location_id', 'loc.name')
            ->orderBy('loc.name', 'ASC')
            ->setParameter('targetId', $targetId);
    
    if (!$scope->isAll()) {
      $qb->andWhere('sllb.location_id IN (:locationIds)')
               ->setParameter('locationIds', $scope->locationIds);
    }
    
    $results = $qb->getQuery()->getResult();
    
    return array_map(function($row) {
            return [
              'location_id' => (int)$row['location_id'],
              'location_name' => $row['location_name'],
              'qty' => (float)$row['qty'],
              'reserved' => (float)$row['reserved'],
              'available' => (float)$row['qty'] - (float)$row['reserved'],
            ];
        }, $results);
  }
  
  /**
   * Get aggregated quantities for multiple stock targets
   * 
   * Useful for dashboard cards showing many items at once.
   * 
   * Returns:
   * [
   *   123 => ['total' => 15.0, 'reserved' => 2.0, 'available' => 13.0],
   *   124 => ['total' => 8.5, 'reserved' => 0.0, 'available' => 8.5],
   * ]
   */
  public function getBulkQty(array $targetIds, LocationScope $scope): array
  {
    if (empty($targetIds)) {
      return [];
    }
    
    $paramArrays = array();
    $paramArrayTypes = array();

    $query = "
SELECT
  sl.stock_target_id,
  SUM(sllb.qty) as total,
  SUM(sllb.reserved_qty) as reserved
FROM stock_lot as sl
INNER JOIN stock_lot_location_balance sllb ON (sllb.stock_lot_id = sl.id)
WHERE sl.stock_target_id IN (?)
";
    $paramArrays[] = $targetIds;
    $paramArrayTypes[] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
    
    if (!$scope->isAll()) {
      $query .= "
AND sllb.location_id IN (?)
";
      
      $paramArrays[] = $scope->locationIds;
      $paramArrayTypes[] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
    }

    $query .= "
GROUP BY sl.stock_target_id
";

    $results = $this->connection->executeQuery($query, $paramArrays, $paramArrayTypes)->fetchAllAssociative();
    
    $output = [];
    foreach ($results as $row) {
      $targetId = (int)$row['stock_target_id'];
      $total = (float)($row['total'] ?? 0);
      $reserved = (float)($row['reserved'] ?? 0);
      
      $output[$targetId] = [
        'total' => $total,
        'reserved' => $reserved,
        'available' => $total - $reserved,
      ];
    }
    
    foreach ($targetIds as $targetId) {
      if (!isset($output[$targetId])) {
        $output[$targetId] = [
          'total' => 0.0,
          'reserved' => 0.0,
          'available' => 0.0,
        ];
      }
    }
    
    return $output;
  }
  
  /**
   * Check if a target has sufficient quantity in the scope
   */
  public function hasSufficientQty(int $targetId, float $requiredQty, LocationScope $scope): bool
  {
    $current = $this->getCurrentQty($targetId, $scope);
    return $current['available'] >= $requiredQty;
  }
  
  /**
   * Get low stock targets within a scope
   * 
   * Returns targets where current_qty <= reorder_point
   */
  public function getLowStockTargets(LocationScope $scope, int $limit = 50): array
  {
    $qb = $this->connection->createQueryBuilder();
        
    $subQb = $this->connection->createQueryBuilder();
    $subQb->select('sl.stock_target_id', 'SUM(sllb.qty) as total_qty')
            ->from('stock_lot', 'sl')
            ->innerJoin('sl', 'stock_lot_location_balance', 'sllb', 'sllb.stock_lot_id = sl.id')
            ->groupBy('sl.stock_target_id');
    
    if (!$scope->isAll()) {
      $subQb->andWhere('sllb.location_id IN (:locationIds)');
    }
    
    $qb->select('st.id', 'st.name', 'st.reorder_point', 'qty_data.total_qty')
            ->from('stock_target', 'st')
            ->innerJoin('st', '(' . $subQb->getSQL() . ')', 'qty_data', 'qty_data.stock_target_id = st.id')
            ->where('qty_data.total_qty <= st.reorder_point')
            ->orderBy('CASE WHEN qty_data.total_qty <= 0 THEN 1 ELSE 2 END', 'ASC')
            ->addOrderBy('qty_data.total_qty', 'ASC')
            ->setMaxResults($limit);
    
    if (!$scope->isAll()) {
      $qb->setParameter('locationIds', $scope->locationIds);
    }
    
    return $qb->getQuery()->getResult();
  }
}
