<?php
namespace App\Katzen\Repository;

use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeListRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, RecipeList::class);
  }

  public function save(RecipeList $recipeList): void
  {
    $this->getEntityManager()->persist($recipeList);
    $this->getEntityManager()->flush();
  }

  /**
   * Find all recipe lists (menus) containing a specific recipe
   *
   * @return RecipeList[]
   */
  public function findListsContainingRecipe(Recipe $recipe): array
  {
    return $this->createQueryBuilder('rl')
        ->innerJoin('rl.recipes', 'r')
        ->andWhere('r = :recipe')
        ->setParameter('recipe', $recipe)
        ->getQuery()
        ->getResult();
  }
}
