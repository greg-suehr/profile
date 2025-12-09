<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
  #[Route('/beal', name: 'client_landing')]
  public function landing(Request $request): Response
  {
    $host = $request->getHost();
    # TODO: host routing

    return $this->render('client/home.html.twig');
  }

  #[Route('/beal/bio', name: 'client_bio')]
  public function bio(Request $request): Response { return $this->render('client/bio.html.twig'); }

  #[Route('/beal/contact', name: 'client_contact')]
  public function contact(Request $request): Response { return $this->render('client/contact.html.twig'); }

  #[Route('/beal/work', name: 'client_work')]
  public function work(Request $request): Response { return $this->render('client/work.html.twig'); }

  
}
