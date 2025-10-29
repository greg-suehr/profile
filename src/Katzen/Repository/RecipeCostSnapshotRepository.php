<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeCostSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecipeCostSnapshot>
 */
class RecipeCostSnapshotRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, RecipeCostSnapshot::class);
  }

  public function save(RecipeCostSnapshot $entity, bool $flush = true): void
  {
    $this->getEntityManager()->persist($entity);
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Get the most recent snapshot for a recipe
   */
  public function findLatest(Recipe $recipe, ?string $calculationMethod = null): ?RecipeCostSnapshot
  {
    $qb = $this->createQueryBuilder('rcs')
            ->where('rcs.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->orderBy('rcs.calculated_at', 'DESC')
            ->setMaxResults(1);

    if ($calculationMethod) {
      $qb->andWhere('rcs.calculation_method = :method')
         ->setParameter('method', $calculationMethod);
    }

    return $qb->getQuery()->getOneOrNullResult();
  }

  /**
   * Get cost history for a recipe
   * 
   * @return RecipeCostSnapshot[]
   */
  public function findHistory(
    Recipe $recipe,
    ?\DateTimeInterface $since = null,
    int $limit = 100
  ): array
  {
    $qb = $this->createQueryBuilder('rcs')
            ->where('rcs.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->orderBy('rcs.calculated_at', 'DESC')
            ->setMaxResults($limit);

    if ($since) {
      $qb->andWhere('rcs.calculated_at >= :since')
          ->setParameter('since', $since);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find snapshots by calculation method
   * 
   * @return RecipeCostSnapshot[]
   */
  public function findByMethod(string $method, int $limit = 50): array
  {
    return $this->createQueryBuilder('rcs')
            ->where('rcs.calculation_method = :method')
            ->setParameter('method', $method)
            ->orderBy('rcs.calculated_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Get average cost for a recipe over a time period
   */
  public function getAverageCost(
    Recipe $recipe,
    int $days = 30,
    ?string $calculationMethod = null
  ): ?float
  {
    $since = (new \DateTime())->modify("-{$days} days");

    $qb = $this->createQueryBuilder('rcs')
            ->select('AVG(rcs.total_cost) as avg_cost')
            ->where('rcs.recipe = :recipe')
            ->andWhere('rcs.calculated_at >= :since')
            ->setParameter('recipe', $recipe)
            ->setParameter('since', $since);

    if ($calculationMethod) {
      $qb->andWhere('rcs.calculation_method = :method')
          ->setParameter('method', $calculationMethod);
    }

    $result = $qb->getQuery()->getOneOrNullResult();
    
    return $result['avg_cost'] ? (float)$result['avg_cost'] : null;
  }

  /**
   * Find recipes with the highest cost increases
   * 
   * @return array<array{recipe: Recipe, old_cost: float, new_cost: float, increase_pct: float}>
   */
  public function findCostIncreases(
    int $compareDays = 30,
    float $thresholdPct = 10.0,
    int $limit = 20
  ): array
  {
    $compareDate = (new \DateTime())->modify("-{$compareDays} days");
    
    $recentSnapshots = $this->createQueryBuilder('rcs')
            ->where('rcs.calculated_at >= :compare_date')
            ->setParameter('compare_date', $compareDate)
            ->orderBy('rcs.recipe', 'ASC')
            ->addOrderBy('rcs.calculated_at', 'DESC')
            ->getQuery()
            ->getResult();

    // Group by recipe and compare oldest to newest
    $recipeCosts = [];
    foreach ($recentSnapshots as $snapshot) {
      $recipeId = $snapshot->getRecipe()->getId();
      $cost = (float)$snapshot->getTotalCost();
      
      if (!isset($recipeCosts[$recipeId])) {
        $recipeCosts[$recipeId] = [
          'recipe' => $snapshot->getRecipe(),
          'newest' => $cost,
          'oldest' => $cost,
        ];
      } else {
        $recipeCosts[$recipeId]['oldest'] = $cost;
      }
    }
    
    // Calculate increases
    $increases = [];
    foreach ($recipeCosts as $data) {
      if ($data['oldest'] > 0) {
        $increasePct = (($data['newest'] - $data['oldest']) / $data['oldest']) * 100;
        
        if ($increasePct >= $thresholdPct) {
          $increases[] = [
            'recipe' => $data['recipe'],
            'old_cost' => $data['oldest'],
            'new_cost' => $data['newest'],
            'increase_pct' => $increasePct,
          ];
        }
      }
    }
    
    // Sort by increase percentage and limit
    usort($increases, fn($a, $b) => $b['increase_pct'] <=> $a['increase_pct']);
    
    return array_slice($increases, 0, $limit);
  }
  
  /**
   * Get cost trend for a recipe (increasing, decreasing, stable)
   */
  public function getCostTrend(Recipe $recipe, int $days = 30): string
  {
    $snapshots = $this->findHistory($recipe, (new \DateTime())->modify("-{$days} days"));
    
    if (count($snapshots) < 2) {
      return 'insufficient_data';
    }
    
    // Compare first half to second half
    $costs = array_map(fn($s) => (float)$s->getTotalCost(), $snapshots);
    $midpoint = (int)(count($costs) / 2);
    
    $recentAvg = array_sum(array_slice($costs, 0, $midpoint)) / max(1, $midpoint);
    $olderAvg = array_sum(array_slice($costs, $midpoint)) / max(1, count($costs) - $midpoint);
    
    if ($olderAvg == 0) {
      return 'unknown';
    }
    
    $changePct = (($recentAvg - $olderAvg) / $olderAvg) * 100;
    
    if ($changePct > 5) {
      return 'increasing';
    } elseif ($changePct < -5) {
      return 'decreasing';
    } else {
      return 'stable';
    }
  }

  /**
   * Delete old snapshots (keep only recent X days)
   */
  public function purgeOld(int $keepDays = 365): int
  {
    $cutoffDate = (new \DateTime())->modify("-{$keepDays} days");
        
    return $this->createQueryBuilder('rcs')
            ->delete()
            ->where('rcs.calculated_at < :cutoff')
            ->andWhere('rcs.calculation_method != :standard') // Keep all standard cost snapshots
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('standard', 'standard')
            ->getQuery()
            ->execute();
  }
}
