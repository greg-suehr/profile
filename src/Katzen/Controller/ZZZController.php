<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\DashboardContextService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: 'getkatzen.com')]
final class ZZZController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
    ) {}
    
    #[Route('/coming-soon', name: 'todo')]
    public function index(Request $request): Response
    {
        $page_key = $request->query->get('for');

        switch ($page_key) {
        case 'allergen':
          $text = 'Allergen tracking coming soon!';
        default:
          $text = 'This feature is coming soon.';
        }
                  
        return $this->render('katzen/coming_soon.html.twig', $this->dashboardContext->with([
          'text' => $text,
        ]));
    }
}
