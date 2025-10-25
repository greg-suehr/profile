<?php

namespace App\Katzen\Service\Utility;

use App\Katzen\Entity\KatzenUser;
use App\Katzen\Service\Utility\DashboardMetricsService;

final class AlertService
{
    public function __construct(
        private DashboardMetricsService $metrics,
    ) {}
    
    public function getAlertsForContext(
        ?KatzenUser $user, 
        string $route, 
        ?array $routeParams = null
    ): array {
        if (!$user) {
            return [];
        }

        $metrics = $this->metrics->getMetrics($user);
        
        return match(true) {
            str_starts_with($route, 'menu_') => $this->getMenuAlerts($routeParams, $metrics),
            str_starts_with($route, 'order_') => $this->getOrderAlerts($metrics),
            str_starts_with($route, 'dashboard_') => $this->getDashboardAlerts($metrics),
            default => []
        };
    }
    
    private function getDashboardAlerts(DashboardMetrics $metrics): array
    {
        $alerts = [];
        
        if ($metrics->stockHealth === 'critical') {
            $alerts[] = [
                'type' => 'danger',
                'alert_text' => sprintf('%d items critically low in stock', $metrics->lowStockItemCount),
                'actionRoute' => 'stock_index',
                'actionLabel' => 'Review Stock'
            ];
        }
        
        if ($metrics->orderLoad === 'slammed') {
          $alerts[] = [
            'type' => 'warning',
            'alert_text' => sprintf('%d orders pending.', $metrics->pendingOrderCount),
            'actionRoute' => 'order_index',
            'actionLabel' => 'Manage Orders'
          ];
        }
        
        return $alerts;
    }
    
    private function getMenuAlerts(?array $params, DashboardMetrics $metrics): array
    {
      $alerts = [];
        
      if ($metrics->lowStockItemCount > 0 && isset($params['id'])) {
        $lowStockInMenu = []; # TODO: this
        
        if (!empty($lowStockInMenu)) {
          $alerts[] = new Alert(
            type: 'warning',
            message: sprintf(
              '%d item(s) on this menu are low in stock',
              count($lowStockInMenu)
                    ),
            actionRoute: 'stock_index',
            actionLabel: 'Review Stock',
            metadata: ['items' => $lowStockInMenu]
          );
        }
      }
        
      return $alerts;
  }

  private function getOrderAlerts(): array
  {
    $alerts = [];
    
    $upcomingOrders = []; # TODO: tie into OrderService method
    if (!empty($upcomingOrders)) {
      $alerts[] = [
        'type' => 'info',
        'alert_text' => sprintf(
          '%d order(s) scheduled within 48 hours',
          count($upcomingOrders)
                ),
        'actionRoute' => 'schedule_index',
        'actionLabel' => 'View Schedule'
      ];
    }
    
    return $alerts;
  }
}
