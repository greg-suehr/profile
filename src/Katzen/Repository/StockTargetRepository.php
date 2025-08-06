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

//    /**
//     * @return StockTarget[] Returns an array of StockTarget objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

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
