<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LitmasController extends AbstractController
{
    #[Route('/litmas', name: 'litmas_index')]
    public function index(): Response
    {
        return $this->render('litmas/info.html.twig', [
          'storyNodeKey' => 'litmas',
          'showCanvas'   => true,
        ]);
    }

    #[Route('/litmas/rsvp', name: 'litmas_rsvp')]
    public function rsvp(Request $request): Response
    {
        // Handle form submit or load form template
        return $this->render('litmas/rsvp.html.twig');
    }

      #[Route('/litmas/location', name: 'litmas_location')]
    public function location(Request $request): Response
    {
        // Handle form submit or load form template
        return $this->render('litmas/location.html.twig');
    }

    #[Route('/litmas/faq', name: 'litmas_faq')]
    public function faq(Request $request): Response
    {
        // Handle form submit or load form template
        return $this->render('litmas/faq.html.twig');
    }  

    #[Route('/litmas/admin', name: 'litmas_admin')]
    public function admin(): Response
    {
        // Access-controlled interface to view RSVP data
        return $this->render('litmas/admin.html.twig');
    }
}
