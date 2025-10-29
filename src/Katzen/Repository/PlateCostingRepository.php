<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\PlateCosting;
use App\Katzen\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlateCosting>
 */
class PlateCostingRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, PlateCosting::class);
  }
  
  public function save(PlateCosting $cost): void
  {
    $this->getEntityManager()->persist($cost);
    $this->getEntityManager()->flush();
  }

  /**
   * Find all plates with a specific cost status
   * 
   * @return PlateCosting[]
   */
  public function findByStatus(string $status): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.cost_status = :status')
            ->setParameter('status', $status)
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find plates exceeding a food cost percentage threshold
   * 
   * @return PlateCosting[]
   */
  public function findExceedingThreshold(float $threshold): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.current_food_cost_pct > :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find plates with alerts enabled that are out of range
   * 
   * @return PlateCosting[]
   */
  public function findAlertsTriggered(): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.alert_enabled = :enabled')
            ->andWhere('pc.cost_status IN (:statuses)')
            ->setParameter('enabled', true)
            ->setParameter('statuses', ['warning', 'critical'])
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get summary statistics for all plate costings
   * 
   * @return array{
   *   total_items: int,
   *   avg_food_cost_pct: float,
   *   on_target: int,
   *   warning: int,
   *   critical: int
   * }
   */
  public function getSummaryStats(): array
  {
    $qb = $this->createQueryBuilder('pc')
            ->select(
              'COUNT(pc.id) as total_items',
              'AVG(pc.current_food_cost_pct) as avg_food_cost_pct',
              'SUM(CASE WHEN pc.cost_status = :on_target THEN 1 ELSE 0 END) as on_target',
              'SUM(CASE WHEN pc.cost_status = :warning THEN 1 ELSE 0 END) as warning',
              'SUM(CASE WHEN pc.cost_status = :critical THEN 1 ELSE 0 END) as critical'
            )
            ->setParameter('on_target', 'on_target')
            ->setParameter('warning', 'warning')
            ->setParameter('critical', 'critical');

    $result = $qb->getQuery()->getOneOrNullResult();
    
    return [
      'total_items' => (int)($result['total_items'] ?? 0),
      'avg_food_cost_pct' => (float)($result['avg_food_cost_pct'] ?? 0.0),
      'on_target' => (int)($result['on_target'] ?? 0),
      'warning' => (int)($result['warning'] ?? 0),
      'critical' => (int)($result['critical'] ?? 0),
    ];
  }

  /**
   * Find plates needing price updates
   * (high food cost % and price not updated recently)
   * 
   * @return PlateCosting[]
   */
  public function findNeedingPriceUpdate(int $daysSinceUpdate = 90): array
  {
    $cutoffDate = (new \DateTime())->modify("-{$daysSinceUpdate} days");
        
    return $this->createQueryBuilder('pc')
            ->where('pc.cost_status IN (:statuses)')
            ->andWhere('pc.price_last_updated IS NULL OR pc.price_last_updated < :cutoff')
            ->setParameter('statuses', ['warning', 'critical'])
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get top performers (lowest food cost percentage)
   * 
   * @return PlateCosting[]
   */
  public function findTopPerformers(int $limit = 10): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.current_price > 0')
            ->orderBy('pc.current_food_cost_pct', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Get worst performers (highest food cost percentage)
   * 
   * @return PlateCosting[]
   */
  public function findWorstPerformers(int $limit = 10): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.current_price > 0')
            ->orderBy('pc.current_food_cost_pct', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Find plates by cost range
   * 
   * @return PlateCosting[]
   */
  public function findByCostRange(float $minCost, float $maxCost): array
  {
    return $this->createQueryBuilder('pc')
            ->where('pc.current_cost >= :min')
            ->andWhere('pc.current_cost <= :max')
            ->setParameter('min', $minCost)
            ->setParameter('max', $maxCost)
            ->orderBy('pc.current_cost', 'ASC')
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find plates with significant variance from target
   * 
   * @return PlateCosting[]
   */
  public function findWithVariance(float $minVariancePct = 5.0): array
  {
    return $this->createQueryBuilder('pc')
            ->where('ABS(pc.current_food_cost_pct - pc.target_food_cost_pct) >= :variance')
            ->andWhere('pc.target_food_cost_pct IS NOT NULL')
            ->setParameter('variance', $minVariancePct)
            ->orderBy('ABS(pc.current_food_cost_pct - pc.target_food_cost_pct)', 'DESC')
            ->getQuery()
            ->getResult();
  }
}
