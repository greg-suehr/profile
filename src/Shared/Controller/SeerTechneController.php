<?php

namespace App\Shared\Controller;

use App\Shared\Service\SeerTechne\SeerTechnePuzzle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%seertechne_match%'")]
final class SeerTechneController extends AbstractController
{
  public function __construct(
    private readonly SeerTechnePuzzle $puzzle,
  ) {}

  #[Route('/', name: 'seertechne_landing')]
  public function techne(Request $request): Response
  {
    $request->getSession()->start();

    return $this->render('seertechne/index.html.twig', [
      'markerCount' => $this->puzzle->length(),
      'puzzleToken' => $this->csrfToken(),
    ]);
  }

  #[Route('/contact', name: 'seertechne_contact')]
  public function contact(): Response
  {
    return $this->render('seertechne/contact.html.twig');
  }

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

  private function csrfToken(): string
  {
    return $this->container->has('security.csrf.token_manager')
      ? $this->container->get('security.csrf.token_manager')->getToken('seertechne_puzzle')->getValue()
      : '';
  }
}
