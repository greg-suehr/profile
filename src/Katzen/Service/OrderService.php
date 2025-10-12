<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use App\Katzen\Messenger\Message\AsyncTaskMessage;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\OrderItemRepository;
use App\Katzen\Service\InventoryService;
use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\Service\StockTargetAutogenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class OrderService
{
  public function __construct(
    private OrderRepository $orderRepo,
    private OrderItemRepository $itemRepo,
    private EntityManagerInterface $em,
    private InventoryService $inventoryService,
    private StockTargetAutogenerator $autogenerator,
    private RecipeExpanderService $expander,
    private MessageBusInterface $bus,
  ) {}

  public function createOrder(Order $order, array $recipeQuantities): void
  {
    $recipes = $this->em->getRepository(Recipe::class)
                        ->findBy(['id' => array_keys($recipeQuantities)]);
        
    foreach ($recipes as $recipe) {
      $this->autogenerator->ensureExistsForRecipe($recipe);

      $qty = $recipeQuantities[$recipe->getId()] ?? 1;
      
      $item = new OrderItem();
      $item->setRecipeListRecipeId($recipe);
      $item->setQuantity($qty);
      $item->setOrderId($order);
      $order->addOrderItem($item);
    }

    $order->setStatus('pending');
    $this->em->persist($order);
    $this->em->flush();
  }

  public function updateOrder(Order $order, array $recipeIds): void
  {
    $existingItems = $order->getOrderItems();
    $existingRecipeIds = [];
    
    foreach ($existingItems as $item) {
      $existingId = $item->getRecipeListRecipeId()->getId();
      if (!in_array($existingId, $recipeIds)) {
        $order->removeOrderItem($item);
        $this->em->remove($item);
      } else {
        $existingRecipeIds[] = $existingId;
      }
    }
    
    $newRecipeIds = array_diff($recipeIds, $existingRecipeIds);
    if (!empty($newRecipeIds)) {
      $newRecipes = $this->em->getRepository(Recipe::class)
                             ->findBy(['id' => $newRecipeIds]);
      
      foreach ($newRecipes as $recipe) {
        $this->autogenerator->ensureExistsForRecipe($recipe);
        
        $item = new OrderItem();
        $item->setRecipeListRecipeId($recipe);
        $item->setQuantity(1);
        $item->setOrderId($order);
        $order->addOrderItem($item);
      }
    }
    
    $order->setStatus('pending');
    $this->em->persist($order);
    $this->em->flush();
  }
  
  public function completeOrder(Order $order): void
  {
    foreach ($order->getOrderItems() as $orderLine) {
      $recipe = $orderLine->getRecipeListRecipeId();
      if (!$recipe) {
        throw new \RuntimeException("Order item missing recipe");
      }

      $expanded = $this->expander->getStockConsumptions($recipe, $orderLine->getQuantity());

      foreach ($expanded as $consumption) {
        $this->bus->dispatch(new AsyncTaskMessage(
          taskType: 'consume_stock',
          payload: [
            'stock_target.id' => $consumption['target']->getId(),
            'quantity' => $consumption['quantity'],
            'reason'   => 'order#' . $order->getId(),
          ]
        ));
      }
    }
    
    $order->setStatus('complete');
    $this->em->flush();
  }

  public function checkStockForOpenOrders(): ServiceResponse
  {
    try {
      $openOrders = $this->orderRepo->findByStatus('pending');
      
      if (empty($openOrders)) {
        return ServiceResponse::success(
          data: [
            'success' => [],
            'error' => [],
          ],
          message: 'No open orders to check'
        );
      }

      $aggregatedRequirements = $this->aggregateStockRequirements($openOrders);

      $result = $this->inventoryService->bulkCheckStock($aggregatedRequirements);

      return ServiceResponse::success(
        data: $result,
        message: sprintf('Checked stock for %d open orders', count($openOrders))
            );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: 'Failed to check stock: ' . $e->getMessage(),
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  private function aggregateStockRequirements(array $orders): array
  {
    $aggregated = [];

    foreach ($orders as $order) {
      foreach ($order->getOrderItems() as $orderItem) {
        $recipe = $orderItem->getRecipeListRecipeId();
        if (!$recipe) {
          continue;
                }
        
        $consumptions = $this->expander->getStockConsumptions(
          $recipe, 
          $orderItem->getQuantity()
        );
        
        foreach ($consumptions as $consumption) {
          $targetId = $consumption['target']->getId();
          $qty = $consumption['quantity'];
          
          if (!isset($aggregated[$targetId])) {
            $aggregated[$targetId] = 0.0;
          }
          $aggregated[$targetId] += $qty;
        }
      }
    }

    return $aggregated;
  }
      
}
