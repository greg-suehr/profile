<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Service\StockTargetAutogenerator;
use App\Katzen\Repository\StockTargetRepository;
use Doctrine\ORM\EntityManagerInterface;

interface RecipeExpanderInterface
{
    /**
     * @return array<array{target: StockTarget, quantity: float}>
     */
    public function getStockConsumptions(Recipe $recipe, int $servings): array;
}


final class RecipeExpanderService implements RecipeExpanderInterface
{

  public function __construct(
    private EntityManagerInterface $em,
    private StockTargetRepository $repo,
    private StockTargetAutogenerator $generator,
  )    
  {}
  
  public function getStockConsumptions(Recipe $recipe, int $multiplier): array
  {
    $results = [];

    foreach ($recipe->getRecipeIngredients() as $ingredient) {
      $supply = $this->generator->getStockTarget($ingredient);
      if (!$supply) {
        throw new \RuntimeException("Missing supply info for ingredient in recipe {$recipe->getTitle()}");
      }

      $type = $ingredient->getSupplyType();
      
      $qtyPerServing = $ingredient->getQuantity();
      $totalQty = $qtyPerServing * $multiplier;
      
      $results[] = [
        'target' => $supply,
        'quantity' => $totalQty,
      ];
    }
    
    return $results;
  }
}
