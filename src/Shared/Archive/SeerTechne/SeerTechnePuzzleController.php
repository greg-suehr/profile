<?php

declare(strict_types=1);

namespace App\Shared\Archive\SeerTechne;

use App\Shared\Service\SeerTechne\SeerTechnePuzzle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeerTechnePuzzleController extends AbstractController
{
  public function __construct(
    private readonly SeerTechnePuzzle $puzzle,
  ) {}

  #[Route('/_st/p', name: 'seertechne_puzzle_press', methods: ['POST'])]
  public function press(Request $request): JsonResponse
  {
    $payload = json_decode($request->getContent() ?: '[]', true);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => false, 'step' => 0], Response::HTTP_BAD_REQUEST);
    }

    if (!$this->isCsrfTokenValid('seertechne_puzzle', (string) ($payload['t'] ?? ''))) {
      return new JsonResponse(['ok' => false, 'step' => 0], Response::HTTP_FORBIDDEN);
    }

    $marker = filter_var($payload['n'] ?? null, FILTER_VALIDATE_INT);
    if ($marker === false || $marker < 0 || $marker >= $this->puzzle->length()) {
      return new JsonResponse(['ok' => false, 'step' => 0], Response::HTTP_BAD_REQUEST);
    }

    $result = $this->puzzle->press($request->getSession(), $marker);

    $response = new JsonResponse(
      $result->toArray(),
      $result->locked ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_OK,
    );
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');

    return $response;
  }

  public function csrfToken(): string
  {
    return $this->container->has('security.csrf.token_manager')
      ? $this->container->get('security.csrf.token_manager')->getToken('seertechne_puzzle')->getValue()
      : '';
  }
}
