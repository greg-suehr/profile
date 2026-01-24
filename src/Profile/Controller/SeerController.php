<?php

namespace App\Profile\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '{domain}', requirements: ['domain' => '%gregishere_hosts%'], defaults: ['domain' => 'gregishere.com'])]
final class SeerController extends AbstractController
{

  public function __construct(
  ) {}

  #[Route('/slide', name: 'seer_tmp')]
  public function tmp(): Response { return $this->render('seer/slide-effect.html.twig', []);}

  #[Route('/design', name: 'seer_home')]
  public function index(): Response { return $this->render('seer/index.html.twig', []);}

  #[Route('/about', name: 'seer_about')]
  public function about(): Response { return $this->render('seer/about.html.twig', []);}

  #[Route('/contact', name: 'seer_contact')]
  public function contact(): Response  { return $this->render('seer/contact.html.twig', []);}
  
  #[Route('/custom', name: 'seer_custom')]
  public function custom(): Response { return $this->render('seer/custom.html.twig', []);}

  #[Route('/work', name: 'seer_work')]
  public function work(): Response { return $this->render('seer/work.html.twig', []);}
  
  #[Route('/faq', name: 'seer_faq')]
  public function faq(): Response { return $this->render('seer/faq.html.twig', []);}
}
