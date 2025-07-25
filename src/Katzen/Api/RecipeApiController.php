<?php

namespace App\Katzen\Api;

use App\Katzen\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class RecipeApiController extends AbstractController
{
    #[Route('/api/recipes/search', name: 'api_recipes_search', methods: ['GET'])]
    public function search(Request $request, RecipeRepository $repo): JsonResponse
    {
        $q = $request->query->get('q', '');
        if (strlen(trim($q)) < 2) {
            return $this->json([]);
        }

        $recipes = $repo->findByTitleLike($q);

        $data = array_map(fn($r) => [
            'id'   => $r->getId(),
            'title' => $r->getTitle(),
        ], $recipes);

        return $this->json($data);
    }
}
