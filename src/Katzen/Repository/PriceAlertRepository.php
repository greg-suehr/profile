<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\PriceAlert;
use App\Katzen\Entity\StockTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceAlert>
 */
class PriceAlertRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, PriceAlert::class);
  }

  public function save(PriceAlert $alert): void
  {
    $this->getEntityManager()->persist($alert);
    $this->getEntityManager()->flush();
  }

  /**
   * Find active alerts for a stock target
   * 
   * @return PriceAlert[]
   */
  public function findActiveForTarget(StockTarget $stockTarget): array
  {
    return $this->createQueryBuilder('pa')
            ->where('pa.stock_target = :target')
            ->andWhere('pa.enabled = :true')
            ->setParameter('target', $stockTarget)
            ->setParameter('true', true)
            ->getQuery()
            ->getResult();
  }

  /**
   * Find recently triggered alerts
   * 
   * @return PriceAlert[]
   */
  public function findRecentlyTriggered(int $days = 7, int $limit = 50): array
  {
    $since = (new \DateTime())->modify("-{$days} days");

    return $this->createQueryBuilder('pa')
            ->where('pa.last_triggered_at >= :since')
            ->setParameter('since', $since)
            ->orderBy('pa.last_triggered_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find alerts by type
   * 
   * @return PriceAlert[]
   */
  public function findByType(string $alertType, bool $enabledOnly = true): array
  {
    $qb = $this->createQueryBuilder('pa')
            ->where('pa.alert_type = :type')
            ->setParameter('type', $alertType)
            ->orderBy('pa.stock_target', 'ASC');

    if ($enabledOnly) {
      $qb->andWhere('pa.enabled = :true')
               ->setParameter('true', true);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get alert statistics
   * 
   * @return array{
   *   total_alerts: int,
   *   enabled: int,
   *   triggered_last_week: int,
   *   by_type: array
   * }
   */
  public function getStatistics(): array
  {
    $all = $this->findAll();
    $oneWeekAgo = (new \DateTime())->modify('-7 days');
    
    $stats = [
      'total_alerts' => count($all),
      'enabled' => 0,
      'triggered_last_week' => 0,
      'by_type' => [],
    ];
    
    foreach ($all as $alert) {
      if ($alert->isEnabled()) {
        $stats['enabled']++;
      }
      
      $type = $alert->getAlertType();
      $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
      
      if ($alert->getLastTriggeredAt() && $alert->getLastTriggeredAt() >= $oneWeekAgo) {
        $stats['triggered_last_week']++;
      }
    }
    
    return $stats;
  }
  
  /**
   * Find alerts that haven't been triggered in X days (potentially stale)
   * 
   * @return PriceAlert[]
   */
  public function findStale(int $days = 90): array
  {
    $cutoff = (new \DateTime())->modify("-{$days} days");
        
    return $this->createQueryBuilder('pa')
            ->where('pa.enabled = :true')
            ->andWhere('pa.last_triggered_at IS NULL OR pa.last_triggered_at < :cutoff')
            ->setParameter('true', true)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('pa.last_triggered_at', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find most frequently triggered alerts
   * 
   * @return PriceAlert[]
   */
  public function findMostTriggered(int $limit = 10): array
  {
    return $this->createQueryBuilder('pa')
            ->where('pa.trigger_count > 0')
            ->orderBy('pa.trigger_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }
}
