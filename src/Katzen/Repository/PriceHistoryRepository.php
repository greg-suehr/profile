<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\PriceHistory;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceHistory>
 */
class PriceHistoryRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, PriceHistory::class);
  }

  public function save(PriceHistory $history): void
  {
    $this->getEntityManager()->persist($history);
    $this->getEntityManager()->flush();
  }

  /**
   * Find price history for a stock target, optionally filtered by vendor
   * 
   * @return PriceHistory[]
   */
  public function findByStockTarget(
    StockTarget $stockTarget,
    ?Vendor $vendor = null,
    ?\DateTimeInterface $since = null,
    int $limit = 100
  ): array
  {
    $qb = $this->createQueryBuilder('ph')
            ->where('ph.stock_target = :target')
            ->setParameter('target', $stockTarget)
            ->orderBy('ph.effective_date', 'DESC')
            ->setMaxResults($limit);

    if ($vendor) {
      $qb->andWhere('ph.vendor = :vendor')
         ->setParameter('vendor', $vendor);
    }
    
    if ($since) {
      $qb->andWhere('ph.effective_date >= :since')
         ->setParameter('since', $since);
    }
    
    return $qb->getQuery()->getResult();
  }

  /**
   * Get the most recent price for a stock target from a vendor
   */
  public function findLatestPrice(StockTarget $stockTarget, Vendor $vendor): ?PriceHistory
  {
    return $this->createQueryBuilder('ph')
            ->where('ph.stock_target = :target')
            ->andWhere('ph.vendor = :vendor')
            ->setParameter('target', $stockTarget)
            ->setParameter('vendor', $vendor)
            ->orderBy('ph.effective_date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
  }

  /**
   * Calculate average price for a stock target over a period
   */
  public function getAveragePrice(
    StockTarget $stockTarget,
    ?Vendor $vendor = null,
    int $days = 30
  ): ?float
  {
    $since = (new \DateTime())->modify("-{$days} days");

    $qb = $this->createQueryBuilder('ph')
            ->select('AVG(ph.unit_price) as avg_price')
            ->where('ph.stock_target = :target')
            ->andWhere('ph.effective_date >= :since')
            ->setParameter('target', $stockTarget)
            ->setParameter('since', $since);

    if ($vendor) {
      $qb->andWhere('ph.vendor = :vendor')
         ->setParameter('vendor', $vendor);
    }

    $result = $qb->getQuery()->getOneOrNullResult();
    
    return $result['avg_price'] ? (float)$result['avg_price'] : null;
  }

  /**
   * Get price statistics for a stock target
   * 
   * @return array{min: float, max: float, avg: float, count: int}|null
   */
  public function getPriceStatistics(
    StockTarget $stockTarget,
    ?Vendor $vendor = null,
    int $days = 90
  ): ?array
  {
    $since = (new \DateTime())->modify("-{$days} days");

    $qb = $this->createQueryBuilder('ph')
            ->select(
                'MIN(ph.unit_price) as min_price',
                'MAX(ph.unit_price) as max_price',
                'AVG(ph.unit_price) as avg_price',
                'COUNT(ph.id) as price_count'
            )
            ->where('ph.stock_target = :target')
            ->andWhere('ph.effective_date >= :since')
            ->setParameter('target', $stockTarget)
            ->setParameter('since', $since);

    if ($vendor) {
      $qb->andWhere('ph.vendor = :vendor')
         ->setParameter('vendor', $vendor);
    }

    $result = $qb->getQuery()->getOneOrNullResult();

    if (!$result || $result['price_count'] == 0) {
      return null;
    }

    return [
      'min' => (float)$result['min_price'],
      'max' => (float)$result['max_price'],
      'avg' => (float)$result['avg_price'],
      'count' => (int)$result['price_count'],
    ];
  }

  /**
   * Find items with significant price increases
   * 
   * @return array<array{stock_target: StockTarget, old_price: float, new_price: float, increase_pct: float}>
   */
  public function findPriceIncreases(
    float $thresholdPct = 10.0,
    int $compareDays = 30
  ): array
  {
    $compareDate = (new \DateTime())->modify("-{$compareDays} days");

    // This is a complex query - might need raw SQL or separate queries
    $qb = $this->createQueryBuilder('ph')
            ->select('ph.stock_target', 'ph.unit_price', 'ph.effective_date')
            ->where('ph.effective_date >= :compare_date')
            ->setParameter('compare_date', $compareDate)
            ->orderBy('ph.stock_target', 'ASC')
            ->addOrderBy('ph.effective_date', 'DESC');
    
    $results = $qb->getQuery()->getResult();

    $increases = [];
    $targetPrices = [];
    
    foreach ($results as $record) {
      $targetId = $record['stock_target']->getId();
      
      if (!isset($targetPrices[$targetId])) {
        $targetPrices[$targetId] = [
          'target' => $record['stock_target'],
          'newest' => (float)$record['unit_price'],
          'oldest' => (float)$record['unit_price'],
        ];
      } else {
        $targetPrices[$targetId]['oldest'] = (float)$record['unit_price'];
      }
    }

    foreach ($targetPrices as $data) {
      if ($data['oldest'] > 0) {
        $increasePct = (($data['newest'] - $data['oldest']) / $data['oldest']) * 100;
        
        if ($increasePct >= $thresholdPct) {
          $increases[] = [
            'stock_target' => $data['target'],
            'old_price' => $data['oldest'],
            'new_price' => $data['newest'],
            'increase_pct' => $increasePct,
          ];
        }
      }
    }
    
    return $increases;
  }
}
