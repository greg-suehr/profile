<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, OrderItem::class);
  }

  public function add(OrderItem $item): void
  {
    $this->getEntityManager()->persist($item);
  }
  
  public function save(OrderItem $item): void
  {
    $this->getEntityManager()->persist($item);
    $this->getEntityManager()->flush();
  }

  public function remove(OrderItem $item): void
  {
    $this->getEntityManager()->remove($item);
    $this->getEntityManager()->flush();
  }

  /**
   * Find all order lines referencing a specific recipe
   *
   * @return OrderLine[]
   */
  public function findByRecipe(Recipe $recipe): array
  {
    return $this->createQueryBuilder('oi')
        ->andWhere('oi.recipe_list_recipe_id = :recipe')
        ->setParameter('recipe', $recipe)
        ->getQuery()
        ->getResult();
  }
}
