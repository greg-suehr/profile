<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
  #[Route(path: '/login', name: 'katzen_login', condition: "request.getHost() matches '%katzen_match%'")]
  #[Route(path: '/login', name: 'blog_login', condition: "request.getHost() matches '%gregishere_match%'")]
  #[Route(path: '/login', name: 'listmas_login', condition: "request.getHost() matches '%litmas_match%'")]
  public function login(AuthenticationUtils $authenticationUtils): Response
  {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

  #[Route(path: '/logout', name: 'katzen_logout', condition: "request.getHost() matches '%katzen_match%'")]
  #[Route(path: '/logout', name: 'blog_logout', condition: "request.getHost() matches '%gregishere_match%'")]
  #[Route(path: '/logout', name: 'listmas_logout', condition: "request.getHost() matches '%litmas_match%'")]
  public function logout(): void
  {
    throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
  }
}
