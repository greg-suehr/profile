<?php

namespace App\Katzen\Controller;

use App\Katzen\Dashboard\Widget\WidgetRegistry;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupplyDashController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private WidgetRegistry $widgets,
    ) {}
    
    #[Route('/supply', name: 'supply_home')]
    public function index(): Response
    {
        // Get widgets relevant to supply operations
        // TODO: Filter widgets by supply domain context
        $views = array_map(fn($v) => $v->toArray(), $this->widgets->all());

        return $this->render('katzen/manager/dashboard.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'dashboard',                    
            'activeMenu' => null,
            'widgets' => $views,
        ]));
    }
}
