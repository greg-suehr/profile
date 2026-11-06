<?php
namespace App\Katzen\Controller;

use App\Katzen\Service\Cook\RecipeImportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
#[IsGranted('ROLE_USER')]
class RecipeImportController
{
    public const CSRF_TOKEN_ID = 'recipe_import';

    private RecipeImportService $importService;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(RecipeImportService $importService, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->importService = $importService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Callers send the token from csrf_token('recipe_import') as an
     * X-CSRF-Token header (JSON body) or a _token field (multipart upload).
     */
    #[Route('/import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

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
