<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use App\Katzen\Messenger\Message\AsyncTaskMessage;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\OrderItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\InventoryService;
use App\Katzen\Service\Inventory\StockTargetAutogenerator;
use App\Katzen\Service\Order\RequirementsPlanner;
use App\Katzen\Service\Response\ServiceResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final class OrderService
{
  public function __construct(
    private OrderRepository $orderRepo,
    private OrderItemRepository $itemRepo,
    private RecipeRepository $recipeRepo,
    private RequirementsPlanner $requirements,
    private AccountingService $accounting,
    private InventoryService $inventoryService,
    private StockTargetAutogenerator $autogenerator,
    private RecipeExpanderService $expander,
    private MessageBusInterface $bus,
  ) {}

  /**
   * Create a new order from a recipe => quantity array.
   *
   * @param array<int,int|float> $recipeQuantities
   */
  public function createOrder(Order $order, array $recipeQuantities): ServiceResponse
  {
     try {
       if (empty($recipeQuantities)) {
         return ServiceResponse::failure(
           errors: ['No recipes provided.'],
           message: 'Order not created: empty recipe list.',
           data: ['order_id' => $order->getId()]
         );
       }
       
       $recipes = $this->recipeRepo->findBy(['id' => array_keys($recipeQuantities)]);

       if (empty($recipes)) {
         return ServiceResponse::failure(
           errors: ['No matching recipes found for provided IDs.'],
           message: 'Order not created: invalid recipe IDs.',
           data: ['order_id' => $order->getId()]
         );
       }

       $missingIds = array_diff(array_keys($recipeQuantities), array_map(fn($r) => $r->getId(), $recipes));
       $errors = [];
       if (!empty($missingIds)) {
         $errors[] = sprintf('Unknown recipe IDs: %s', implode(', ', $missingIds));
       }

       $orderSubtotal = 0.00;
       $orderTaxAmount = 0.00;
       # TODO: sort out Order, Menu, Recipe, Item, StockTarget associations...
       foreach ($recipes as $recipe) {
         $this->autogenerator->ensureExistsForRecipe($recipe);

         $qty = (float) ($recipeQuantities[$recipe->getId()] ?? 1);
         if ($qty <= 0) {
           $errors[] = sprintf('Quantity must be positive for recipe %d.', $recipe->getId());
           continue;
         }
      
         $item = new OrderItem();
         $item->setRecipeListRecipeId($recipe);
         $item->setQuantity($qty);
         $item->setUnitPrice(1.00); # TODO: initialize OrderItem Price from pricing service
         $item->setCogs(0.00);      # TODO: initialize expected COGS from costing service         
         $item->setOrderId($order);
         $order->addOrderItem($item);
         $orderSubtotal += $item->getItemSubtotal();
         $orderTaxAmount += 0.00; # TODO: design OrderItem level tax rules
       }

       if (!empty($errors)) {
         return ServiceResponse::failure(
           errors: $errors,
           message: 'Order not created due to validation errors.',
           data: ['order_id' => $order->getId()]
         );
       }
       $order->setSubtotal($orderSubtotal);
       $order->setTaxAmount($orderTaxAmount);
       $order->setFulfillmentStatus('unfulfilled');
       $order->setStatus('pending');
       $this->orderRepo->save($order);

       return ServiceResponse::success(
         data: [
           'order_id'   => $order->getId(),
           'item_count' => count($order->getOrderItems()),
           'status'     => 'pending',
         ],
         message: 'Order created.'
       );
     } catch (\Throwable $e) {
       return ServiceResponse::failure(
         errors: ['Failed to create order: ' . $e->getMessage()],
         message: 'Unhandled exception while creating order.',
         data: ['order_id' => $order->getId()],
         metadata: [
           'exception' => get_class($e),
           'code'      => (int) $e->getCode(),
         ]
       );
     }
  }     

  /**
   * Replace the order’s recipes with the provided list (default qty=1 for new ones).
   *
   * @param int[] $recipeIds
   */
  public function updateOrder(Order $order, array $recipeIds): ServiceResponse
  {
    try {
      $existingItems = $order->getOrderItems();
      $existingRecipeIds = [];
    
      foreach ($existingItems as $item) {
        $existingId = $item->getRecipeListRecipeId()->getId();

        if ($existingId === null) {
          $order->removeOrderItem($item);
          $this->itemRepo->remove($item);
          continue;
        }
        
        if (!in_array($existingId, $recipeIds, true)) {
          $order->removeOrderItem($item);
          $this->itemRepo->remove($item);
        } else {
          $existingRecipeIds[] = $existingId;
        }
      }
    
      $newRecipeIds = array_values(array_diff($recipeIds, $existingRecipeIds));
      if (!empty($newRecipeIds)) {
        $newRecipes = $this->recipeRepo->findBy(['id' => $newRecipeIds]);
        
        $foundIds = array_map(fn($r) => $r->getId(), $newRecipes);
        $invalidIds = array_diff($newRecipeIds, $foundIds);
        
        if (!empty($invalidIds)) {
          return ServiceResponse::failure(
            errors: [sprintf('Unknown recipe IDs: %s', implode(', ', $invalidIds))],
            message: 'Order not updated due to invalid recipe IDs.',
            data: ['order_id' => $order->getId()]
          );
        }
        
        foreach ($newRecipes as $recipe) {
          # TODO: remove shim after shoring up input data validation
          $this->autogenerator->ensureExistsForRecipe($recipe);
        
          $item = new OrderItem();
          $item->setRecipeListRecipeId($recipe);
          $item->setQuantity(1);
          $item->setOrderId($order);
          $order->addOrderItem($item);
        }
      }
    
      $order->setStatus('pending');
      $this->orderRepo->save($order);

      return ServiceResponse::success(
        data: [
          'order_id'   => $order->getId(),
          'item_count' => count($order->getOrderItems()),
          'status'     => 'pending',
        ],
        message: 'Order updated.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to update order: ' . $e->getMessage()],
        message: 'Unhandled exception while updating order.',
        data: ['order_id' => $order->getId()],
        metadata: [
          'exception' => get_class($e),
          'code'      => (int) $e->getCode(),
        ]
      );
    }
  }

  /**
   * Update order status and generate stock consumption events.
   */
  public function completeOrder(Order $order): ServiceResponse
  {
    try {
      if ($order->getStatus() === 'complete') {
        return ServiceResponse::success(
          data: [
            'order_id'       => $order->getId(),
            'enqueued_tasks' => 0,
            'status'         => 'already_complete',
          ],
          message: 'Order is already marked complete; no work performed.'
        );
      }

      $warnings      = [];
      $enqueuedTasks = 0;
      
      $plan = $this->requirements->plan(['orders'=>[$order]], purpose: 'consume', groupBy: 'stockTarget');

      if ($plan['errors']) {
        return ServiceResponse::failure(
          errors: $plan['errors'],
          data: $plan,
          message: 'Unit conversion failed for one or more ingredients.',
          metadata: [
            'order_id' => $order->getId()
          ]
        );
      }

      foreach ($plan['requirements'] as $req) {
        if ($req->totalBaseQty <= 0) {
          $warnings[] = 'No stock consumption record for order item: invalid quantity.';
          continue;
        }
        if (!$req->target?->getId()) {
          $warnings[] = 'No stock consumption record for order item %s: invalid stock target.';
          continue;
        }
        
        $this->bus->dispatch(new AsyncTaskMessage(
          taskType: 'consume_stock',
          payload: [
            'stock_target.id' => $req->target?->getId(),
            'quantity' => $req->totalBaseQty,
            'reason'   => 'order#' . $order->getId(),
          ]
        ));
        
        $enqueuedTasks++;
      }     

      # TODO: consider soft-failure and inventory reconciliation instead of
      #       interrupting order completion with Requirement Planning errors
      if (!empty($warnings)) {
        return ServiceResponse::failure(
          errors: $warnings,
          message: 'Order not completed due to errors.',
          data: [
            'order_id'       => $order->getId(),
            'enqueued_tasks' => $enqueuedTasks,
            'status'         => 'blocked',
          ],
          metadata: [
            'order_status' => $order->getStatus(),
          ]
        );
      }

      $r = $this->accounting->recordEvent(
        'unbilled_ar_on_fulfillment',
        [
          'revenue' => $order->getSubtotal(),
          'tax_total' => $order->getTaxAmount(),            
        ],
        'order',
        (string)$order->getId(),
      );
      
      /*
      $this->bus->dispatch(new AsyncTaskMessage(
        taskType: 'record_journal_event',
        payload: [
          'stock_target.id' => $req->target?->getId(),
          'quantity' => $req->totalBaseQty,
          'reason'   => 'order#' . $order->getId(),
        ]
      ));
      */
      
      $order->setStatus('complete');
      $this->orderRepo->flush();
      
      return ServiceResponse::success(
        data: [
          'order_id'       => $order->getId(),
          'enqueued_tasks' => $enqueuedTasks,
          'status'         => 'complete',
        ],
        message: 'Order complete!',
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to complete order: ' . $e->getMessage()],
        message: 'Unhandled exception while completing order.',
        data: [
          'order_id' => $order->getId(),
        ],
        metadata: [
          'exception' => get_class($e),
          'code'      => (int) $e->getCode(),
        ]
      );
    }
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

      $aggregateResults = $this->aggregateStockRequirements($openOrders);
      if ($aggregateResults->isFailure()) {
        return ServiceResponse::failure(
          errors: $result->getErrors(),
          message: sprintf('Unable to generate stock requirements for %d open orders.', count($openOrders)),
          data: $aggregateResults->getData()
        );
      }
      
      $requirements = $aggregateResults->getData()['requirements'];                                                         

      $result = $this->inventoryService->bulkCheckStock($requirements);

      if ($result->isFailure()) {
        return ServiceResponse::failure(
          errors: $result->getErrors(),
          message: sprintf('Checked stock for %d open orders: insufficient stock', count($openOrders)),
          data: $result->getData(),
        );
      }
      
      return ServiceResponse::success(
        data: $result->getData(),
        message: sprintf('Checked stock for %d open orders', count($openOrders))
            );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to check stock: ' . $e->getMessage()],
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  private function aggregateStockRequirements(array $orders): ServiceResponse
  {
    $errors = [];
    try {
      $requirements = [];
      
      foreach ($orders as $order) {
        foreach ($order->getOrderItems() as $orderItem) {
          $recipe = $orderItem->getRecipeListRecipeId();
          if (!$recipe) {    
            $errors[] = sprintf('No recipe for order item %d.', $orderItem->getId());
            continue;
          }
        
          $consumptions = $this->expander->getStockConsumptions(
            $recipe, 
            $orderItem->getQuantity()
          );
        
          foreach ($consumptions as $consumption) {
            $target = $consumption['target'] ?? null;
            $qty = $consumption['quantity'] ?? null;

            if (!$target || $qty === null) {
              $errors[] = sprintf('No target or quantity for order item %d.', $orderItem->getId());
              continue;
            }

            $targetId = $target->getId();
            if (!isset($requirements[$targetId])) {
              $requirements[$targetId] = 0.0;
            }
            $requirements[$targetId] += (float) $qty;
          }
        }
      }

      return ServiceResponse::success(
        data: ['requirements' => $requirements],
        message: 'Aggregated stock requirements.',
        metadata: ['errors' => $errors]
      );
    } catch (\Throwable $e) {      
      return ServiceResponse::failure(
        errors: ['Failed to aggregate requirements: ' . $e->getMessage()],
        message: 'Unhandled exception while aggregating stock requirements.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
}
