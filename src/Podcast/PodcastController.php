<?php

namespace App\Podcast\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PodcastController extends AbstractController
{
    #[Route('/show', name: 'podcast_home')]
    public function index(Request $request): Response
    {
        return $this->render('podcast/home.html.twig', [
        ]);
    }
}
