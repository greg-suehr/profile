<?php

namespace App\Katzen\Dashboard\Widget;

use App\Katzen\Repository\StockTargetRepository;

final class LowStockWidget implements WidgetInterface
{
  public function __construct(
    private StockTargetRepository $stockRepo,
  ) {}

  public function getKey(): string { return 'kpi.items.stock.low'; }
  
  public function getType(): string
  {
    return 'low_stock';
  }

  public function getTitle(): string
  {
    return 'Low Stock Alert';
  }

  public function getIcon(): string
  {
    return 'fas fa-exclamation-triangle';
  }

  public function getData(): array
  {
    $lowStockItems = $this->stockRepo->findLowStockTargets();
        
    $items = [];
    foreach ($lowStockItems as $target) {
      $currentQty = (float) $target->getCurrentQty();
      $reorderPoint = (float) $target->getReorderPoint();
      
      $items[] = [
        'id' => $target->getId(),
        'name' => $target->getName(),
        'current_qty' => $currentQty,
        'reorder_point' => $reorderPoint,
        'unit' => $target->getBaseUnit()?->getName() ?? 'units',
        'status' => $this->getStockStatus($currentQty, $reorderPoint),
        'days_remaining' => $this->estimateDaysRemaining($target),
      ];
    }
    
    return [
      'items' => $items,
      'total_count' => count($items),
      'critical_count' => count(array_filter($items, fn($item) => $item['status'] === 'critical')),
    ];
    }
  
  public function getTemplate(): string
  {
    return 'katzen/dashboard/widgets/low_stock.html.twig';
  }

  public function getViewModel(): WidgetView
  {
    $data = $this->getData();

    $subtitle = sprintf('%d low / %d out', $data['total_count'], $data['critical_count']);
    $tone = $data['critical_count'] > 0 ? 'error' : ($data['total_count'] > 0 ? 'warning' : 'success');
    
    return new WidgetView(
      key: $this->getKey(),
      title: 'Low Stock',
      value: (string)$data['total_count'],
      subtitle: $subtitle,
      tone: $tone,
    );
  }
  
  public function getPriority(): int
  {
    return 90; // High priority
  }

  public function toArray(): array
  {
    return [
      'type' => $this->getType(),
      'title' => $this->getTitle(),
      'icon' => $this->getIcon(),
      'data' => $this->getData(),
      'template' => $this->getTemplate(),
      'priority' => $this->getPriority(),
    ];
  }
  
  private function getStockStatus(float $currentQty, float $reorderPoint): string
  {
    if ($currentQty <= 0) {
      return 'out_of_stock';
    }
    
    if ($currentQty <= ($reorderPoint * 0.5)) {
      return 'critical';
    }
    
    if ($currentQty <= $reorderPoint) {
      return 'low';
    }
    
    return 'ok';
  }
  
  private function estimateDaysRemaining(object $target): ?int
  {
    $currentQty = (float) $target->getCurrentQty();
    return null;
    /* TODO: enhance with historical usage data
    $dailyUsage = (float) $target->getEstimatedDailyUsage() ?? 0;
    $dailyUsage = 0;
    
    if ($dailyUsage <= 0) {
      return null;
    }
        
    return (int) ceil($currentQty / $dailyUsage);
    */
  }
}
