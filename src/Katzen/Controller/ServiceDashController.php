<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Dashboard\Widget\WidgetRegistry;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
final class ServiceDashController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private WidgetRegistry $widgets,
    ) {}

    #[Route('/service', name: 'service_dashboard')]
    #[DashboardLayout('service', 'dashboard', 'service-dashboard')]
    public function index(): Response
    {
        // Get widgets relevant to supply operations
        // TODO: Filter widgets by supply domain context
        $views = array_map(fn($v) => $v->toArray(), $this->widgets->all());

        return $this->render('katzen/widgets/dashboard.html.twig', $this->dashboardContext->with([
            'widgets' => $views,
        ]));
    }
}
