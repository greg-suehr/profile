<?php

namespace App\Katzen\Controller;

use App\Katzen\Dashboard\Widget\WidgetRegistry;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrepDashController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private WidgetRegistry $widgets,
    ) {}
    
    #[Route('/prep', name: 'prep_dashboard')]
    public function index(): Response
    {
        // Get widgets relevant to prep operations
        // TODO: Filter widgets by prep domain context
        $views = array_map(fn($v) => $v->toArray(), $this->widgets->all());

        return $this->render('katzen/widgets/dashboard.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-prep.html.twig',
            'activeItem' => 'dashboard',                    
            'activeMenu' => null,
            'widgets' => $views,
        ]));
    }
}
