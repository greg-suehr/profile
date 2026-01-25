<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Dashboard\Widget\WidgetRegistry;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class FinanceDashController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private WidgetRegistry $widgets,
    ) {}

  #[Route('/finance', name: 'finance_dashboard')]
  #[DashboardLayout('finance', 'dashboard', 'finance-dashboard')]
  public function index(): Response
  {
      # TODO: per context, role, user Dashboard Widget selection
      $views = array_map(fn($v) => $v->toArray(), $this->widgets->all());

      return $this->render('katzen/widgets/dashboard.html.twig', $this->dashboardContext->with([
        'widgets' => $views,
      ]));
  } 
}
