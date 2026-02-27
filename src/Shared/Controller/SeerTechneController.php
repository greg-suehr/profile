<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%seertechne_match%'")]
final class SeerTechneController extends AbstractController
{
  #[Route('/', name: 'seertechne_landing')]
  public function techne(): Response { return $this->render('seertechne/index.html.twig'); }

  #[Route('/work/simulations', name: 'seertechne_simulations')]
  public function simulations(): Response
  {
    return $this->render('greg/simulations.html.twig');
  }
}
