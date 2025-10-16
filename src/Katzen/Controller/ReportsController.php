<?php

namespace App\Katzen\Controller;

use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportsController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private AccountingService $accountingService,
    ) {}

    #[Route('/reports/aging', name: 'reports_aging')]
    public function aging(Request $request): Response
    {
        $result = $this->accountingService->getAgingReport();

        if ($result->isFailure()) {
            $this->addFlash('danger', 'Failed to generate aging report.');
            return $this->redirectToRoute('dashboard_home');
        }

        $data = $result->getData();

        return $this->render('katzen/reports/aging.html.twig', $this->dashboardContext->with([
            'activeItem' => 'reports',
            'activeMenu' => 'accounting',
            'summary' => $data['summary'],
            'customers' => $data['by_customer'],
            'generated_at' => $data['generated_at'],
        ]));
    }

    #[Route('/reports/revenue', name: 'reports_revenue')]
    public function revenue(Request $request): Response
    {
        // TODO: Implement revenue reporting
        return $this->render('katzen/reports/revenue.html.twig', $this->dashboardContext->with([
            'activeItem' => 'reports',
            'activeMenu' => 'accounting',
        ]));
    }
}
