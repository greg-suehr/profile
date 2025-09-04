<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
  #[Route('/', name: 'profile_landing')]
  public function landing(Request $request): Response
  {
    $host = $request->getHost();
    
    if ($host === 'gregsuehr.com') {
      return $this->render('hyper_link/story.html.twig');
    }
    
    return $this->render('greg/dark.html.twig');
  } 

  # TODO: complete
  #[Route('/about', name: 'profile_about')]
  public function overview(): Response
  {
    return $this->render('greg/about.html.twig');
  }

  # TODO: complete
  #[Route('/work', name: 'profile_work')]
  public function work(): Response
  {
    return $this->render('greg/work.html.twig');
  }

  #[Route('/hire', name: 'profile_hire')]
  public function hire(): Response
  {
    return $this->render('greg/hire.html.twig');
  }  
    
  #[Route('/plays', name: 'profile_plays')]
  public function plays(): Response
  {
    # TODO: feed data from CMS
    return $this->render('greg/plays.html.twig');
  }

  #[Route('/contact', name: 'profile_contact')]
  public function contact(): Response
  {
    return $this->render('greg/contact.html.twig');
  }
  
  #[Route('/hire/develop', name: 'profile_hire_me_tech')]
  public function hire_me_tech(): Response
  {
    return $this->render('greg/hire-me-tech.html.twig');
  }

  #[Route('/hire/consult', name: 'profile_hire_me_consult')]
  public function hire_me_consult(): Response
  {
   return $this->render('greg/hire-me-consult.html.twig');
  }

  #[Route('/hire/create', name: 'profile_hire_me_arts')]
  public function hire_me_arts(): Response
  {
    return $this->render('greg/hire-me-arts.html.twig');
  }

  #[Route('/hire/research', name: 'profile_hire_me_research')]
  public function hire_me_research(): Response
  {
    return $this->render('greg/hire-me-research.html.twig');
  }

  #[Route('/plays/purchase', name: 'profile_plays_purchase')]
  public function plays_purchase(): Response
  {
    return $this->render('greg/plays-purchase.html.twig');
  }

  #[Route('/plays/read', name: 'profile_plays_read')]
  public function plays_read(): Response
  {
    return $this->render('greg/plays-read.html.twig');
  }

  #[Route('/poems', name: 'profile_poems')]
  public function poems(): Response
  {
    return $this->render('greg/poems.html.twig');
  }

  #[Route('/poems/purchase', name: 'profile_poems_purchase')]
  public function poems_purchase(): Response
  {
    return $this->render('greg/poems-purchase.html.twig');
  }

  #[Route('/poems/read', name: 'profile_poems_read')]
  public function poems_read(): Response
  {
    # TODO: fetch by ID from CMS
    return $this->render('greg/poems-read.html.twig');
  }

  #[Route('/work/simulations', name: 'profile_simulations')]
  public function simulations(): Response
  {
    return $this->render('greg/simulations.html.twig');
  }
}
