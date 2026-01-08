<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
  #[Route('/resume', name: 'profile_resume')]
  public function resume(): Response { return $this->render('professional/index.html.twig'); }

  #[Route('/research', name: 'profile_research')]
  public function research(): Response { return $this->render('professional/research.html.twig'); }
  
  #[Route('/archive', name: 'profile_archive')]
  public function archive(): Response {
      $projectsPath = $this->getParameter('kernel.project_dir') . '/assets/data/projects.json';
      $projects = [];
      
      if (file_exists($projectsPath)) {
        $projectsJson = file_get_contents($projectsPath);
        $projects = json_decode($projectsJson, true) ?? [];
      }
      
      return $this->render('professional/archive.html.twig', [
        'projects' => $projects
      ]
      );
  }

  # TODO: add links
  #[Route('/about', name: 'profile_about')]
  public function overview(): Response
  {
    return $this->render('greg/about.html.twig');
  }

  # TODO: add links
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

  # TODO: this one
  #[Route('/hire/develop', name: 'profile_hire_me_develop')]
  public function hire_me_tech(): Response
  {
    return $this->render('greg/hire-me-develop.html.twig');
  }

  #[Route('/hire/consult', name: 'profile_hire_me_consult')]
  public function hire_me_consult(): Response
  {
   return $this->render('greg/hire-me-consult.html.twig');
  }

  #[Route('/hire/create', name: 'profile_hire_me_create')]
  public function hire_me_create(): Response
  {
    return $this->render('greg/hire-me-create.html.twig');
  }

  #[Route('/hire/research', name: 'profile_hire_me_research')]
  public function hire_me_research(): Response
  {
    return $this->render('greg/hire-me-research.html.twig');
  }

  # TODO: implement payments
  #[Route('/plays/purchase', name: 'profile_plays_purchase')]
  public function plays_purchase(): Response
  {
    return $this->render('greg/plays-purchase.html.twig');
  }

  # TODO: implement PDF sample file hosting
  #[Route('/plays/read', name: 'profile_plays_read')]
  public function plays_read(): Response
  {
    return $this->render('greg/plays-read.html.twig');
  }

  # TODO: implement PDF sample file hosting  
  #[Route('/poems', name: 'profile_poems')]
  public function poems(): Response
  {
    return $this->render('greg/poems.html.twig');
  }

  # TODO: implement payments
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
