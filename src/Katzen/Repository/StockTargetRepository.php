<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockTarget>
 */
class StockTargetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockTarget::class);
    }

  public function countAll(): int
  {
    return (int) $this->createQueryBuilder('s')
        ->select('COUNT(s.id)')
        ->getQuery()
        ->getSingleScalarResult();
  }

  public function countByStatus(array|string $statuses): int
  {
    $statuses = (array) $statuses;
    return (int) $this->createQueryBuilder('s')
        ->select('COUNT(s.id)')
        ->andWhere('s.status IN (:st)')
        ->setParameter('st', $statuses)
        ->getQuery()
        ->getSingleScalarResult();
  }

  public function findOneByItemId($value): ?StockTarget
  {
    return $this->createQueryBuilder('s')
        ->join('s.item', 'i')
        ->andWhere('i.id = :val')
        ->setParameter('val', $value)
        ->getQuery()        
        ->getOneOrNullResult();
    }

  public function findOneByRecipeId($value): ?StockTarget
  {
    return $this->createQueryBuilder('s')
        ->join('s.recipe', 'r')
        ->andWhere('r.id = :val')
        ->setParameter('val', $value)
        ->getQuery()
        ->getOneOrNullResult()
        ;
    }

  /**
   * Find stock targets that are at or below their reorder point
   * 
   * @return StockTarget[]
   */
  public function findLowStockTargets(): array
  {
    return $this->createQueryBuilder('st')
        ->where('st.current_qty <= st.reorder_point')
        ->andWhere('st.status = :active')
        ->setParameter('active', 'OK') // Assuming 'OK' means active
        ->orderBy('CASE 
            WHEN st.current_qty <= 0 THEN 1
            WHEN st.current_qty <= (st.reorder_point * 0.5) THEN 2  
            ELSE 3
        END', 'ASC') // Out of stock first, then critical, then low
        ->addOrderBy('st.name', 'ASC')
        ->getQuery()
        ->getResult();
  }
  
  /**
   * Get stock targets that need attention within the next few days
   * 
   * @param int $daysAhead Number of days to look ahead
   * @return StockTarget[]
   */
  public function findStockTargetsNeedingAttention(int $daysAhead = 7): array
  {
    return $this->createQueryBuilder('st')
        ->where('st.current_qty <= st.reorder_point')
        ->orWhere('(st.estimated_daily_usage > 0 AND st.current_qty <= (st.estimated_daily_usage * :days))')
        ->andWhere('st.status = :active')
        ->setParameter('days', $daysAhead)
        ->setParameter('active', 'OK')
        ->orderBy('st.current_qty / NULLIF(st.estimated_daily_usage, 0)', 'ASC') // Days remaining
        ->getQuery()
        ->getResult();
  }
}
