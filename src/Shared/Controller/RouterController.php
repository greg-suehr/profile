<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RouterController extends AbstractController
{
  #[Route('/', name: 'profile_landing')]
  public function landing(Request $request): Response
  {
    $host = $request->getHost();
    
    if ($host === 'mulvaylitmas.com') {
      return $this->redirectToRoute('litmas_index');
    }
    
    if ($host === 'gregsuehr.com') {
      return $this->redirectToRoute('profile_resume');
    }

    if ($host === 'katzendemo.com') {
      return $this->redirectToRoute('katzen_demo');
    }
    
    return $this->render('greg/dark.html.twig');
  }

}
