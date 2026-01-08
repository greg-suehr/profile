<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KatzenDemoController extends AbstractController
{
  #[Route('/demo', name: 'katzen_demo')]
  public function landing(Request $request): Response
  {
    return $this->render('katzen_demo/landing.html.twig');
  }
}
