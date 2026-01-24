<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
  #[Route(path: '/login', name: 'katzen_login', host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
  #[Route(path: '/login', name: 'blog_login', host: '{domain}', requirements: ['domain' => '%gregishere_hosts%'], defaults: ['domain' => 'gregishere.com'])]
  #[Route(path: '/login', name: 'listmas_login', host: '{domain}', requirements: ['domain' => '%litmas_hosts%'], defaults: ['domain' => 'mulvaylitmas.com'])]
  public function login(AuthenticationUtils $authenticationUtils): Response
  {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

  #[Route(path: '/logout', name: 'katzen_logout', host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
  #[Route(path: '/logout', name: 'blog_logout', host: '{domain}', requirements: ['domain' => '%gregishere_hosts%'], defaults: ['domain' => 'gregishere.com'])]
  #[Route(path: '/logout', name: 'listmas_logout', host: '{domain}', requirements: ['domain' => '%litmas_hosts%'], defaults: ['domain' => 'mulvaylitmas.com'])]
  public function logout(): void
  {
    throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
  }
}
