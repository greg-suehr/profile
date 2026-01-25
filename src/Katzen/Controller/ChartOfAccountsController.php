<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Repository\AccountRepository;
use App\Katzen\Service\Accounting\ChartOfAccountsService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class ChartOfAccountsController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private AccountRepository $accountRepo,
        private ChartOfAccountsService $chartService,
    ) {}

    #[Route('/finance/chart-of-accounts', name: 'chart_of_accounts')]
    #[DashboardLayout('finance', 'account', 'chart-of-accounts')]
    public function index(Request $request): Response
    {
        $asOf = $request->query->get('as_of');
        $asOfDate = $asOf ? new \DateTime($asOf) : null;
        
        $showZeroBalances = $request->query->get('show_zero', '0') === '1';
        $expandAll = $request->query->get('expand', '0') === '1';

        $chartData = $this->chartService->buildChartStructure($asOfDate, $showZeroBalances);

        return $this->render('katzen/finance/chart_of_accounts.html.twig', 
            $this->dashboardContext->with([
                'chartData' => $chartData,
                'asOfDate' => $asOfDate,
                'showZeroBalances' => $showZeroBalances,
                'expandAll' => $expandAll,
                'totals' => $this->chartService->calculateTotals($chartData),
            ])
        );
    }

    #[Route('/finance/chart-of-accounts/export', name: 'chart_of_accounts_export')]
    #[DashboardLayout('finance', 'account', 'chart-of-accounts')]
    public function export(Request $request): Response
    {
        $asOf = $request->query->get('as_of');
        $asOfDate = $asOf ? new \DateTime($asOf) : null;
        
        $chartData = $this->chartService->buildChartStructure($asOfDate, true);
        
        // Generate CSV export
        $csv = $this->chartService->exportToCsv($chartData, $asOfDate);
        
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="chart-of-accounts-' . date('Y-m-d') . '.csv"');
        
        return $response;
    }
}
