<?php
namespace App\Katzen\Controller;

use App\Katzen\Service\Cook\RecipeImportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
class RecipeImportController
{
    private RecipeImportService $importService;

    public function __construct(RecipeImportService $importService)
    {
        $this->importService = $importService;
    }

    #[Route('/import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $data = json_decode($request->getContent(), true);

        if ($file) {
            $result = $this->importService->importFile($file);
        } elseif ($data) {
            $result = $this->importService->importData($data);
        } else {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        return new JsonResponse(['success' => true, 'recipe_id' => $result->getId()]);
    }
}
