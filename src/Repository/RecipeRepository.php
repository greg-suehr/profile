<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
        parent::__construct($registry, Recipe::class);
    }

  public function save(Recipe $recipe): void
  {
        $this->getEntityManager()->persist($recipe);
        $this->getEntityManager()->flush();
    }

  public function search(array $filters): array
  {
     $qb = $this->createQueryBuilder('r');
     
     if (!empty($filters['ingredientIds'])) {
        $qb->join('r.recipe_ingredient', 'ri')
           ->join('ri.supply', 's')
           ->andWhere($qb->expr()->in('s.id', ':ingredientIds'))
           ->setParameter('ingredientIds', $filters['ingredientIds']);
    }

    if (!empty($filters['tagSlugs'])) {
        $qb->join('r.tags', 't')
           ->andWhere($qb->expr()->in('t.slug', ':tagSlugs'))
           ->setParameter('tagSlugs', $filters['tagSlugs']);
    }

    if (!empty($filters['cuisine'])) {
        $qb->join('r.tags', 'cuisine_tag')
           ->andWhere('cuisine_tag.type = :cuisineType')
           ->andWhere('cuisine_tag.slug = :cuisineSlug')
           ->setParameter('cuisineType', 'cuisine')
           ->setParameter('cuisineSlug', $filters['cuisine']);
    }

    return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Recipe[] Returns an array of Recipe objects
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

    //    public function findOneBySomeField($value): ?Recipe
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
