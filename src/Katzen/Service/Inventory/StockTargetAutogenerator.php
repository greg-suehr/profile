<?php

namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Service\Inventory\SupplyResolver;
use App\Katzen\Repository\StockTargetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StockTargetAutogenerator
{
    public function __construct(
        private EntityManagerInterface $em,      
        private StockTargetRepository $repo,
        private SupplyResolver $supplyResolver,
    ) {}

    public function getStockTarget(RecipeIngredient $ingredient): StockTarget
    {
      $supply = $ingredient->getSupply($this->em); # TODO: not this
      $type = $ingredient->getSupplyType();

      
      return match ($type) {
        'item' => $this->ensureExistsForItem($supply),
        'recipe' => $this->ensureExistsForRecipe($supply),
        default => throw new UnexpectedValueException("Unknown supply type: $type"),
      };
    }

    public function ensureExistsForRecipe(Recipe $recipe, array &$visited = []): StockTarget
    {
        $id = $recipe->getId();
        if (isset($visited[$id])) {
          return $this->repo->findOneBy(['recipe' => $recipe]);
        }
        $visited[$id] = true;

        if ($target = $this->repo->findOneBy(['recipe' => $recipe])) {
            return $target;
        }

        $target = new StockTarget();
        $target->setRecipe($recipe);
        $target->setBaseUnit($recipe->getServingUnit());
        $target->setName($recipe->getTitle());
        $target->setCurrentQty('0.00');
        $target->setStatus('OK');

        $this->em->persist($target);
        
        # TODO: add recursion guards
        foreach ($recipe->getRecipeIngredients() as $ingredient) {
          $supply = $this->supplyResolver->resolve($ingredient);
          $type = $ingredient->getSupplyType();
          
          if ($type === 'item') {
            $this->ensureExistsForItem($supply);
          }
          else if ($type === 'recipe') {
            $this->ensureExistsForRecipe($supply, $visited);
          }
          else {
            throw new \UnexpectedValueException(sprintf(
              'Unknown supply type "%s" in recipe #%d ("%s")',
              $supply->getSupplyType(),
              $recipe->getId(),
              $recipe->getTitle()
            ));
          }
        }
        
        $this->em->flush();        
        return $target;
    }

    public function ensureExistsForItem(Item $item): StockTarget
    {
        if ($target = $this->repo->findOneBy(['item' => $item])) {
            return $target;
        }

        $target = new StockTarget();
        $target->setItem($item);
#        $target->setBaseUnit(); # TODO: define base unit assignments
        $target->setName($item->getName());
        $target->setCurrentQty('0.00');
        $target->setStatus('OK');

        $this->em->persist($target);
        $this->em->flush();

        return $target;
    }
}

?>
