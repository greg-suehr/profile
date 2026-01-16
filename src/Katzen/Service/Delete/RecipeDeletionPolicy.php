<?php

namespace App\Katzen\Service\Delete;

use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\OrderItemRepository;
use App\Katzen\Repository\RecipeListRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RecipeDeletionPolicy
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecipeRepository $recipes,
        private OrderItemRepository $orderItems,
        private RecipeListRepository $menus
    ) {}

    /**
     * Preflight: enumerate dependencies and say if deletion is OK under the given mode
     */
    public function preflight(Recipe $recipe, DeleteMode $mode): DeleteReport
    {
        $orderRefs = $this->orderItems->findByRecipe($recipe);        
        $menuRefs = $this->menus->findListsContainingRecipe($recipe);       
        $subRecipeRefs = $this->recipes->findRecipesUsingAsIngredient($recipe->getId());

        $facts = [
            'order_ref_count'     => count($orderRefs),
            'order_refs'          => array_slice(
                array_map(fn($ol) => [
                    'id' => $ol->getOrderId()->getId(),
                    'customer' => $ol->getOrderId()->getCustomer()?->getName(),
                    'status' => $ol->getOrderId()->getStatus(),
                ], $orderRefs), 
                0, 10
            ),
            'menu_ref_count'      => count($menuRefs),
            'menu_refs'           => array_slice(
                array_map(fn($m) => ['id' => $m->getId(), 'name' => $m->getName()], $menuRefs),
                0, 10
            ),
            'sub_recipe_ref_count'=> count($subRecipeRefs),
            'sub_recipe_refs'     => array_slice($subRecipeRefs, 0, 10),
        ];

        $reasons = [];
        $ok = true;

        if ($mode === DeleteMode::BLOCK_IF_REFERENCED) {          
            if ($facts['order_ref_count'] > 0) {
                $ok = false;
                $reasons[] = sprintf(
                    'Recipe is used in %d order%s.',
                    $facts['order_ref_count'],
                    $facts['order_ref_count'] === 1 ? '' : 's'
                );
            }
            if ($facts['menu_ref_count'] > 0) {
                $ok = false;
                $reasons[] = sprintf(
                    'Recipe appears on %d menu%s.',
                    $facts['menu_ref_count'],
                    $facts['menu_ref_count'] === 1 ? '' : 's'
                );
            }
            if ($facts['sub_recipe_ref_count'] > 0) {
                $ok = false;
                $reasons[] = sprintf(
                    'Recipe is used as ingredient in %d other recipe%s.',
                    $facts['sub_recipe_ref_count'],
                    $facts['sub_recipe_ref_count'] === 1 ? '' : 's'
                );
            }
        }

        return new DeleteReport($ok, $facts, $reasons);
    }

    /**
     * Execute per mode
     */
    public function execute(Recipe $recipe, DeleteMode $mode): void
    {
        $report = $this->preflight($recipe, $mode);
        if (!$report->ok) {
          throw new \DomainException('Deletion blocked: ' . implode(' ', $report->reasons));
        }

        match ($mode) {
            DeleteMode::BLOCK_IF_REFERENCED => $this->hardDeleteWithCleanup($recipe),
            DeleteMode::SOFT_DELETE => $this->softDelete($recipe),
            DeleteMode::FORCE_WITH_INVALIDATIONS => $this->forceWithInvalidations($recipe, $report),
            default => throw new \LogicException("Delete mode {$mode->value} not supported yet."),
        };
    }

    private function hardDeleteWithCleanup(Recipe $recipe): void
    {
        $menus = $this->menus->findListsContainingRecipe($recipe);
        foreach ($menus as $menu) {
            $menu->removeRecipe($recipe);
        }

        // RecipeIngredients and RecipeInstructions cascade-delete via entity config
        $this->em->remove($recipe);
        try {
          $this->em->flush();
        }
        catch (\Exception $e) {
          dd($e);
        }
    }

    private function softDelete(Recipe $recipe): void
    {
        $recipe->setStatus('archived');
        $this->em->flush();
    }

    private function forceWithInvalidations(Recipe $recipe, DeleteReport $report): void
    {
        $menus = $this->menus->findListsContainingRecipe($recipe);
        foreach ($menus as $menu) {
            $menu->removeRecipe($recipe);
        }
        
        $orderItems = $this->orderItems->findByRecipe($recipe);
        foreach ($orderItems as $item) {
            $item->setRecipeListRecipeId(null);
            // TODO: $item->setNote('Recipe deleted: ' . $recipe->getTitle());
        }
        
        $this->softDelete($recipe);
    }
}
