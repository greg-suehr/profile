<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use UnexpectedValueException;

interface SupplyResolverInterface
{
    public function resolve(RecipeIngredient $ingredient): object;
}

final class SupplyResolver implements SupplyResolverInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function resolve(RecipeIngredient $ingredient): object
    {
        $type = $ingredient->getSupplyType();
        $id = $ingredient->getSupplyId();

        return match ($type) {
            'item' => $this->em->getRepository(Item::class)->find($id),
            'recipe' => $this->em->getRepository(Recipe::class)->find($id),
            default => throw new UnexpectedValueException("Unknown supply type: $type"),
        } ?? throw new \RuntimeException("Supply of type '$type' with ID $id not found");
    }
}
