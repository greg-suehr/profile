<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Tag;
use App\Katzen\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DefaultMenuPlanner
{
    public function __construct(
        private EntityManagerInterface $em,
        private TagRepository $tagRepo,
    ) {}

    public function getActiveMenu(): ?RecipeList
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(RecipeList::class, 'r')
            ->leftJoin(Tag::class, 't', 'WITH', 't.obj = :obj AND t.obj_id = r.id')
            ->setParameter('obj', 'recipe_list')
            ->andWhere('t.type = :type AND t.value IN (:status)')
            ->setParameter('type', 'menu')
            ->setParameter('status', ['current'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAvailableRecipes(): array
    {
        $menu = $this->getActiveMenu();
        if (!$menu) return [];
        return $menu->getRecipes()->toArray();
    }

    public function isRecipeAvailable(Recipe $recipe): bool
    {
        return in_array($recipe, $this->getAvailableRecipes(), true);
    }

    public function getAvailabilityTags(Recipe $recipe): array
    {
        return $this->tagRepo->findByObject('recipe', $recipe->getId(), 'availability');
    }
}
