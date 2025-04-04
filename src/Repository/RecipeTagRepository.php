<?php

namespace App\Repository;

use App\Entity\RecipeTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecipeTag>
 */
class RecipeTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecipeTag::class);
    }

    public function getGeneralTags(): array
    {
      return $this->createQueryBuilder('t')
        ->orderBy('t.value', 'ASC')
        ->getQuery()
        ->getResult();
    }

    public function getCuisineTags(): array
    {
      return $this->createQueryBuilder('t')
        ->andWhere('t.type = :val')
        ->setParameter('val', 'cuisine')
        ->orderBy('t.value', 'ASC')
        ->getQuery()
        ->getResult();
    }
  
    //    /**
    //     * @return RecipeTag[] Returns an array of RecipeTag objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?RecipeTag
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
