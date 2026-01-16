<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Recipe;
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

  public function remove(Recipe $recipe): void
  {
    $this->getEntityManager()->remove($recipe);
  }

  public function flush(): void { $this->getEntityManager()->flush(); }

  public function delete(Recipe $recipe): void
  {
    $this->getEntityManager()->remove($recipe);
    $this->getEntityManager()->flush();
  }

  public function getLatestVersionForTitle(string $title): int
  {
    $versionNum = $this->createQueryBuilder('r')
       ->select('MAX(r.version)')
       ->andWhere('r.title = :title')
       ->setParameter('title', $title)
       ->getQuery()->getSingleScalarResult();

    return $versionNum ?? 0;
  }

  public function findByTitleLike(string $term): array
  {
    return $this->createQueryBuilder('r')
        ->andWhere('r.title ILIKE :term')
        ->setParameter('term', '%'.trim($term).'%')
        ->orderBy('r.title', 'ASC')
        ->setMaxResults(20)
        ->getQuery()
        ->getResult();
  }

  public function findIdsAndTitlesReferencingItem(int $itemId): array
  {
    return $this->createQueryBuilder('r')
        ->select('r.id AS id, r.title AS title')
        ->join('r.recipeIngredients', 'ri')
        ->andWhere('ri.supply_type = :type')
        ->andWhere('ri.supply_id = :id')
        ->setParameter('type', 'item')
        ->setParameter('id', $itemId)
        ->getQuery()
        ->getArrayResult();
    }

  /**
   * Find recipes that use another recipe as an ingredient (sub-recipes)
   * 
   * @return array<array{id: int, title: string}>
   */
  public function findRecipesUsingAsIngredient(int $recipeId): array
  {
    return $this->createQueryBuilder('r')
        ->select('r.id', 'r.title')
        ->innerJoin('r.recipeIngredients', 'ri')
        ->andWhere('ri.supply_type = :type')
        ->andWhere('ri.supply_id = :recipeId')
        ->setParameter('type', 'recipe')
        ->setParameter('recipeId', $recipeId)
        ->getQuery()
        ->getArrayResult();
  }
}
