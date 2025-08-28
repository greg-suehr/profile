<?php

namespace App\Katzen\Dashboard\Widget;

use App\Katzen\Entity\Order;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Service\RecipeExpanderService;
use Doctrine\ORM\EntityManagerInterface;

final class TodaysPrepWidget implements WidgetInterface
{
  public function __construct(
    private OrderRepository $orderRepo,
    private RecipeExpanderService $expander,
    private EntityManagerInterface $em,
  ) {}

  public function getKey(): string { return 'kpi.itemsx.prep.list'; }
  
  public function getType(): string
  {
    return 'todays_prep_list';
  }

  public function getTitle(): string
  {
    return "Today's Prep List";
  }

  public function getIcon(): string
  {
    return 'fas fa-clipboard-list';
  }

  public function getData(): array
  {
    $today = new \DateTimeImmutable('today');
    $tomorrow = $today->modify('+1 day');
    
    // TODO: move to OrderRepository method
    $todaysOrders = $this->em->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->where('o.status = :pending')
            ->andWhere('o.scheduled_at >= :start AND o.scheduled_at < :end')
            ->setParameter('pending', 'pending')
            ->setParameter('start', $today->format('Y-m-d'))
            ->setParameter('end', $tomorrow->format('Y-m-d'))
            ->orderBy('o.scheduled_at', 'ASC')
            ->getQuery()
            ->getResult();

    $prepItems = $this->aggregatePrepRequirements($todaysOrders);
    $orderSummary = $this->buildOrderSummary($todaysOrders);
    
    return [
      'prep_items' => $prepItems,
      'order_summary' => $orderSummary,
      'total_orders' => count($todaysOrders),
      'total_prep_items' => count($prepItems),
      'estimated_prep_time' => $this->calculateTotalPrepTime($todaysOrders),
    ];
  }
  
  public function getTemplate(): string
  {
    return 'katzen/dashboard/widgets/todays_prep_list.html.twig';
  }

  public function getViewModel(): WidgetView
  {
    $data = $this->getData();
    
    $subtitle = sprintf('%d prep items / %d orders\nEstimated prep time: %d',
                        $data['total_prep_items'], $data['total_orders'], $data['estimated_prep_time']);
    
    $tone = 'success';
    
    return new WidgetView(
      key: $this->getKey(),
      title: 'Prep Report',
      value: (string)$data['total_prep_items'],
      subtitle: $subtitle,
      tone: $tone,
    );
  }
  
  public function getPriority(): int
  {
    return 100; // Highest priority
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

  private function aggregatePrepRequirements(array $orders): array
  {
    $aggregatedItems = [];
        
    foreach ($orders as $order) {
      foreach ($order->getOrderItems() as $orderItem) {
        $recipe = $orderItem->getRecipeListRecipeId();
        $quantity = $orderItem->getQuantity();
        
        if (!$recipe) continue;
        
        try {
          $consumptions = $this->expander->getStockConsumptions($recipe, $quantity);
          
          foreach ($consumptions as $consumption) {
            $target = $consumption['target'];
            $requiredQty = $consumption['quantity'];
            $targetId = $target->getId();
                        
            if (!isset($aggregatedItems[$targetId])) {
              $aggregatedItems[$targetId] = [
                'id' => $targetId,
                'name' => $target->getName(),
                'total_required' => 0,
                'current_stock' => (float) $target->getCurrentQty(),
                'unit' => $target->getBaseUnit()?->getName() ?? 'units',
                'recipes_using' => [],
                'sufficient_stock' => true,
              ];
            }
            
            $aggregatedItems[$targetId]['total_required'] += $requiredQty;
            $aggregatedItems[$targetId]['recipes_using'][] = $recipe->getTitle();
            
            if ($aggregatedItems[$targetId]['total_required'] > $aggregatedItems[$targetId]['current_stock']) {
              $aggregatedItems[$targetId]['sufficient_stock'] = false;
            }
          }
        } catch (\Exception $e) {
          error_log("Failed to expand recipe {$recipe->getTitle()}: " . $e->getMessage());
        }
      }
    }
    
    // Remove duplicates
    foreach ($aggregatedItems as &$item) {
      $item['recipes_using'] = array_unique($item['recipes_using']);
    }
    
    uasort($aggregatedItems, function($a, $b) {
        if ($a['sufficient_stock'] !== $b['sufficient_stock']) {
          return $a['sufficient_stock'] ? 1 : -1;
        }
        return strcmp($a['name'], $b['name']);
      });
        
    return array_values($aggregatedItems);
  }

  private function buildOrderSummary(array $orders): array
  {
    $summary = [];
        
    foreach ($orders as $order) {
      $orderData = [
        'id' => $order->getId(),
        'customer' => $order->getCustomer() ?? 'Unknown',
        'scheduled_time' => $order->getScheduledAt()?->format('H:i'),
        'recipes' => [],
        'total_items' => 0,
      ];
      
      foreach ($order->getOrderItems() as $orderItem) {
        $recipe = $orderItem->getRecipeListRecipeId();
        if ($recipe) {
          $orderData['recipes'][] = [
            'name' => $recipe->getTitle(),
            'quantity' => $orderItem->getQuantity(),
          ];
          $orderData['total_items'] += $orderItem->getQuantity();
        }
      }
      
      $summary[] = $orderData;
    }
        
    return $summary;
  }

  private function calculateTotalPrepTime(array $orders): int
  {
    $totalMinutes = 0;
        
    foreach ($orders as $order) {
      foreach ($order->getOrderItems() as $orderItem) {
        $recipe = $orderItem->getRecipeListRecipeId();
        if ($recipe) {
          $quantity = $orderItem->getQuantity();
          $prepTime = $recipe->getPrepTime() ?? 0;
          $cookTime = $recipe->getCookTime() ?? 0;
          
          // TODO: improve time estimates for combined prep
          $totalMinutes += ($prepTime + $cookTime) * $quantity;
        }
      }
    }
    
    return $totalMinutes;
  }
}
