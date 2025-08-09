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
}
