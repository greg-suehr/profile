<?php

namespace App\Shared\Controller;

use App\Shared\PghPoets\Catalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%pghpoets_match%'")]
final class PghPoetsController extends AbstractController
{
  public function __construct(private readonly Catalog $catalog) {}

  #[Route('/', name: 'pghpoets_home')]
  public function home(): Response
  {
    return $this->render('pghpoets/index.html.twig', [
      'poets' => $this->catalog->poets(),
      'news'  => $this->catalog->news(),
    ]);
  }

  #[Route('/about', name: 'pghpoets_about')]
  public function about(): Response
  {
    return $this->render('pghpoets/about.html.twig');
  }

  #[Route('/criteria', name: 'pghpoets_criteria')]
  public function criteria(): Response
  {
    return $this->render('pghpoets/criteria.html.twig', [
      'kinds'     => Catalog::TIE_KINDS,
      'inclusion' => Catalog::INCLUSION,
    ]);
  }

  #[Route('/poets', name: 'pghpoets_poets')]
  public function poets(): Response
  {
    return $this->render('pghpoets/poets.html.twig', [
      'poets'     => $this->catalog->poets(),
      'kinds'     => Catalog::TIE_KINDS,
      'inclusion' => Catalog::INCLUSION,
    ]);
  }

  #[Route('/poets/{slug}', name: 'pghpoets_poet', requirements: ['slug' => '[a-z0-9\-]+'])]
  public function poet(string $slug): Response
  {
    $poet = $this->catalog->poet($slug) ?? throw $this->createNotFoundException();

    return $this->render('pghpoets/poet.html.twig', [
      'poet'      => $poet,
      'kinds'     => Catalog::TIE_KINDS,
      'inclusion' => Catalog::INCLUSION,
    ]);
  }
}
