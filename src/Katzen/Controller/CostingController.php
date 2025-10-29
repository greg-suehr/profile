<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\DashboardContextService;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Form\PriceAlertType;

use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\PriceAlert;
use App\Katzen\Repository\PlateCostingRepository;
use App\Katzen\Repository\PriceAlertRepository;
use App\Katzen\Repository\PriceHistoryRepository;
use App\Katzen\Repository\RecipeCostSnapshotRepository;
use App\Katzen\Repository\VendorRepository;

use App\Katzen\Service\Accounting\CostingService;
use App\Katzen\Service\Accounting\PriceAlertService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/costing', name: 'costing_')]
final class CostingController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private CostingService $costing,
    private PriceAlertService $priceAlerts,
    private PlateCostingRepository $plateCostingRepo,
    private PriceAlertRepository $priceAlertRepo,    
    private PriceHistoryRepository $historyRepo,
    private VendorRepository $vendorRepo,
  )
  {}
  
  // === DASHBOARDS ===
  #[Route('/dashboard', name: 'dashboard')]
  #[DashboardLayout('finance', 'costing', 'costing-dashboard')]
  public function dashboard(Request $request): Response
  {
    $timeRange = $request->query->getInt('days', 90);
    $vendorId = $request->query->get('vendor_id');
    $trendFilter = $request->query->get('trend');

    $qb = $this->historyRepo->createQueryBuilder('ph')
            ->select('ph, st, v')
            ->join('ph.stock_target', 'st')
            ->join('ph.vendor', 'v')
            ->where('ph.effective_date >= :startDate')
            ->setParameter('startDate', new \DateTime("-{$timeRange} days"))
            ->orderBy('ph.effective_date', 'DESC');
        
    if ($vendorId) {
      $qb->andWhere('v.id = :vendorId')
         ->setParameter('vendorId', $vendorId);
    }
        
    $priceHistory = $qb->getQuery()->getResult();
    
    $priceHistoryItems = $this->aggregatePriceData($priceHistory, $timeRange, $trendFilter);
    
    return $this->render('katzen/costing/price_history.html.twig', $this->dashboardContext->with([
      'vendors' => $this->vendorRepo->findBy(['status' => 'active'], ['name' => 'ASC']),
      'priceHistoryItems' => $priceHistoryItems,
      'timeRange' => $timeRange,
      'selectedVendor' => $vendorId,
      'trendFilter' => $trendFilter,
    ]));
  }
    
  // === RECIPE COSTING ===
  #[Route('/recipe/{id}', name: 'recipe')]
  public function recipeDetail(Recipe $recipe): Response
  {
    $costData = $this->costing->getRecipeCostBreakdown($recipe, 1.0);
        
    $plateCosting = $this->plateCostingRepo->findOneBy(['recipe' => $recipe]);
    
    if (!$plateCosting) {      
      $plateCosting = $this->costing->updatePlateCosting($recipe);
    }
        
    $targetFoodCostPct = $plateCosting->getTargetFoodCostPct() 
      ? (float)$plateCosting->getTargetFoodCostPct() 
      : 30.0; # TODO: design cofigurations for default margin %, vanity price rules
            
    $suggestedPrice = $this->costing->calculateTargetPrice($recipe, $targetFoodCostPct);

    return $this->render('katzen/costing/recipe_detail.html.twig', $this->dashboardContext->with([
      'recipe' => $recipe,
      'costData' => $costData,
      'plateCosting' => $plateCosting,
      'suggestedPrice' => $suggestedPrice,
    ]));
  }
    
  #[Route('/menu', name: 'menu')]
  public function menuCostAnalysis(Request $request): Response
  {
    $statusFilter = $request->query->get('status', 'all'); // all, on_target, warning, critical
    
    // Get all plate costings with recipes
    $qb = $this->plateCostingRepo->createQueryBuilder('pc')
            ->join('pc.recipe', 'r')
            ->addSelect('r')
            ->orderBy('pc.current_food_cost_pct', 'DESC');
        
    if ($statusFilter !== 'all') {
      $qb->andWhere('pc.cost_status = :status')
         ->setParameter('status', $statusFilter);
    }
        
    $plateCosting = $qb->getQuery()->getResult();

    $totalItems = count($plateCosting);
    $onTarget = count(array_filter($plateCosting, fn($pc) => $pc->getCostStatus() === 'on_target'));
    $warning = count(array_filter($plateCosting, fn($pc) => $pc->getCostStatus() === 'warning'));
    $critical = count(array_filter($plateCosting, fn($pc) => $pc->getCostStatus() === 'critical'));

    $avgFoodCostPct = $totalItems > 0
      ? array_sum(array_map(fn($pc) => (float)$pc->getCurrentFoodCostPct(), $plateCosting)) / $totalItems
      : 0;

    return $this->render('katzen/costing/menu_analysis.html.twig', $this->dashboardContext->with([
      'plateCosting' => $plateCosting,
      'statusFilter' => $statusFilter,
      'metrics' => [
        'total' => $totalItems,
        'on_target' => $onTarget,
        'warning' => $warning,
        'critical' => $critical,
        'avg_food_cost_pct' => round($avgFoodCostPct, 2),
      ],
    ]));
  }
  
  // === PRICE TRACKING ===
  #[Route('/prices', name: 'price_history')]
  public function priceHistory(Request $request): Response
  {
    $params = $request->query->all();
    return $this->redirectToRoute('costing_dashboard', $params);
  }

  #[Route('/price/{id}', name: 'price_detail')]
  #[DashboardLayout('finance', 'costing', 'price-detail')]
  public function priceDetail(StockTarget $stockTarget, Request $request): Response
  {
    $days = $request->query->getInt('days', 90);
    $vendorId = $request->query->get('vendor_id');
        
    // Get price history for this item
    $qb = $this->priceHistoryRepo->createQueryBuilder('ph')
            ->join('ph.vendor', 'v')
            ->addSelect('v')
            ->where('ph.stock_target = :target')
            ->andWhere('ph.effective_date >= :startDate')
            ->setParameter('target', $stockTarget)
            ->setParameter('startDate', new \DateTime("-{$days} days"))
            ->orderBy('ph.effective_date', 'ASC');
        
    if ($vendorId) {
      $qb->andWhere('v.id = :vendorId')
         ->setParameter('vendorId', $vendorId);
    }

    $priceHistory = $qb->getQuery()->getResult();
        
    // Calculate metrics
    $prices = array_map(fn($ph) => (float)$ph->getUnitPrice(), $priceHistory);
    $metrics = [
      'current' => end($prices) ?: 0,
      'min' => $prices ? min($prices) : 0,
      'max' => $prices ? max($prices) : 0,
      'avg' => $prices ? array_sum($prices) / count($prices) : 0,
      'trend_pct' => $this->calculateTrend($prices),
    ];

    $alerts = $this->priceAlertRepo->findBy(['stock_target' => $stockTarget]);
    
    return $this->render('katzen/costing/price_detail.html.twig', $this->dashboardContext->with([
      'stockTarget' => $stockTarget,
      'priceHistory' => $priceHistory,
      'metrics' => $metrics,
      'alerts' => $alerts,
      'days' => $days,
      'vendors' => $this->vendorRepo->findBy(['active' => true], ['name' => 'ASC']),
    ]));
  }

  #[Route('/alerts', name: 'price_alerts')]
  #[DashboardLayout('finance', 'costing', 'price-alerts-table')]
  public function priceAlerts(Request $request): Response
  {
    $statusFilter = $request->query->get('status', 'active'); // active, triggered, all
    
    $qb = $this->priceAlertRepo->createQueryBuilder('pa')
            ->join('pa.stock_target', 'st')
            ->addSelect('st')
            ->orderBy('pa.last_triggered_at', 'DESC');
    
    if ($statusFilter === 'active') {
      $qb->andWhere('pa.enabled = :enabled')
         ->setParameter('enabled', true);
    } elseif ($statusFilter === 'triggered') {
      $qb->andWhere('pa.last_triggered_at IS NOT NULL')
         ->andWhere('pa.last_triggered_at >= :cutoff')
         ->setParameter('cutoff', new \DateTime('-7 days'));
    }
        
    $alerts = $qb->getQuery()->getResult();
    
    $rows = [];
    foreach ($alerts as $pa) {
      $rows[] = TableRow::create([
        'id' => $pa->getId(),
        'item_name' => $pa->getStockTarget()->getName(),
        'alert_type' => $pa->getAlertType(),
        'threshold_value' => $pa->getThresholdValue(),
        'last_price' => $pa->getLastPrice(),
        'last_triggered_at' => $pa->getLastTriggeredAt(),
      ])
      ->setId($pa->getId());
    }
    
    $table = TableView::create('Price Alerts')
      ->addField(
        TableField::text('item_name', 'Item')->sortable()
          )
      ->addField(
        TableField::text('alert_type', 'Alert Type')->sortable()
          )
      ->addField(
        TableField::amount('threshold_value', 'Threshold')
          )
      ->addField(
        TableField::amount('last_price', 'Last Price')
          )
      ->addField(
        TableField::date('last_triggered', 'Last Triggered')->sortable()
          )
      ->setRows($rows)
      ->setSelectable(true)
      ->setSearchPlaceholder('Search by item name, alert type, ...')
      ->setEmptyState('No price alerts found.')
      ->build();
      
    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'statusFilter' => $statusFilter,
      'bulkRoute' => 'costing_price_alerts_bulk',
      'csrfSlug' => 'costing_price_alerts_bulk',
    ]));
  }

  #[Route('/bulk', name: 'price_alerts_bulk', methods: ['POST'])]
  public function priceAlertsBulk(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('costing_price_alerts_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }

    $action = $payload['action'] ?? null;
    $ids = array_map('intval', $payload['ids'] ?? []);
    
    if (empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'No price alerts selected'], 400);
    }

    $alerts = $this->priceAlertRepo->findBy(['id' => $ids]);
    $count = count($alerts);

    switch ($action) {
    case 'delete':
      foreach ($alerts as $pa) {
        $this->priceAlertRepo->remove($pa);
      }
      $this->priceAlertRepo->flush();
      return $this->json(['ok' => true, 'message' => "$count price alerts(s) deleted"]);

    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
  }
    
  #[Route('/alert/create', name: 'alert_create')]
  #[DashboardLayout('finance', 'costing', 'alert-create')]
  public function createAlert(Request $request): Response
  {
    $alert = new PriceAlert();

    # TODO: document a standard for query param use in Controllers or remove
    $stockTargetId = $request->query->get('stock_target_id');
    if ($stockTargetId) {
      $stockTarget = $this->stockTargetRepo->find($stockTargetId);
      if ($stockTarget) {
        $alert->setStockTarget($stockTarget);
      }
    }

    $form = $this->createForm(PriceAlertType::class, $alert);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {      
      $result = $this->priceAlerts->createAlert(
        $alert->getStockTarget(),
        $alert->getAlertType(),
        $alert->getThresholdValue(),
        null, # TODO: user notifiee list for price alerts
        $alert->getNotifyEmail(),
      );

      if ( $result->isSuccess() ) {
        $this->addFlash('success', 'Price alert created successfully.');              
        return $this->redirectToRoute('costing_price_alerts');
      }


      $this->addFlash('danger', $result->getMessage() ?: 'Unable to create price alert');
      $this->addFlash('warning', implode('; ', (array)$result->getErrors()));
      
      return $this->redirectToRoute('costing_price_alerts');
    }
        
    return $this->render('katzen/costing/alert_create.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
      'alert' => $alert,
    ]));
  }

  #[Route('/alert/toggle/{id}', name: 'alert_toggle', methods: ['POST'])]
  public function toggleAlert(PriceAlert $alert, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('alert_toggle_' . $alert->getId(), $request->request->get('_token'))) {
      throw $this->createAccessDeniedException('Invalid CSRF token');
    }
        
    $alert->setEnabled(!$alert->isEnabled());
    $alert->setUpdatedAt(new \DateTime());
    $this->priceAlertRepo->save($alert, true);
    
    $this->addFlash('success', sprintf(
      'Alert %s successfully.',
      $alert->isEnabled() ? 'enabled' : 'disabled'
    ));
    
    return $this->redirectToRoute('costing_price_alerts');
  }

  #[Route('/alert/delete/{id}', name: 'alert_delete', methods: ['POST'])]
  public function deleteAlert(PriceAlert $alert, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('alert_delete_' . $alert->getId(), $request->request->get('_token'))) {
      throw $this->createAccessDeniedException('Invalid CSRF token');
    }
    
    $this->priceAlertRepo->remove($alert, true);
    
    $this->addFlash('success', 'Alert deleted successfully.');
    
    return $this->redirectToRoute('costing_price_alerts');
  }

  /**
   * Aggregate price history data by stock target with trend calculation
   */
  private function aggregatePriceData(array $priceHistory, int $days, ?string $trendFilter): array
  {
    $grouped = [];
        
    foreach ($priceHistory as $ph) {
      $key = $ph->getStockTarget()->getId() . '_' . $ph->getVendor()->getId();
      
      if (!isset($grouped[$key])) {
        $grouped[$key] = [
          'id' => $key,
          'stockTarget' => $ph->getStockTarget(),
          'vendor' => $ph->getVendor(),
          'prices' => [],
        ];
      }
      
      $grouped[$key]['prices'][] = [
        'date' => $ph->getEffectiveDate()->format('Y-m-d'),
        'price' => (float)$ph->getUnitPrice(),
      ];
    }

    $items = [];
    foreach ($grouped as $key => $data) {
      $prices = array_column($data['prices'], 'price');
      
      if (empty($prices)) continue;
      
      $currentPrice = end($prices);
      $minPrice = min($prices);
      $maxPrice = max($prices);
      $avgPrice = array_sum($prices) / count($prices);
      $trendPct = $this->calculateTrend($prices);
      
      // Determine trend class for badge
      $trendClass = 'secondary';
      if ($trendPct > 5) $trendClass = 'danger';
      elseif ($trendPct > 0) $trendClass = 'warning';
      elseif ($trendPct < -5) $trendClass = 'success';

      $item = [
        'id' => $key,
        'stockTarget' => $data['stockTarget'],
        'vendor' => $data['vendor'],
        'currentPrice' => $currentPrice,
        'minPrice' => $minPrice,
        'maxPrice' => $maxPrice,
        'avgPrice' => $avgPrice,
        'trendPct' => $trendPct,
        'trendClass' => $trendClass,
        'prices' => $data['prices'],
      ];
      
      // Apply trend filter
      if ($trendFilter) {
        if ($trendFilter === 'increase' && $trendPct <= 0) continue;
        if ($trendFilter === 'decrease' && $trendPct >= 0) continue;
        if ($trendFilter === 'stable' && abs($trendPct) > 2) continue;
      }
      
      $items[] = $item;
    }

    // Sort by trend (highest increases first)
    usort($items, fn($a, $b) => $b['trendPct'] <=> $a['trendPct']);
    
    return $items;
  }

  /**
   * Calculate trend percentage from price array
   */
  private function calculateTrend(array $prices): float
  {
    if (count($prices) < 2) return 0.0;
        
    $firstPrice = reset($prices);
    $lastPrice = end($prices);
    
    if ($firstPrice == 0) return 0.0;
        
    return (($lastPrice - $firstPrice) / $firstPrice) * 100;
  }
}
