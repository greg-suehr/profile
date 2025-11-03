<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\{Order, OrderItem};
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\Sellable;
use App\Katzen\Repository\{OrderRepository, OrderItemRepository};
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\SellableRepository;

use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Accounting\CostingService;
use App\Katzen\Service\Cook\RecipeExpanderService;
use App\Katzen\Service\InventoryService;
use App\Katzen\Service\Inventory\StockTargetAutogenerator;
use App\Katzen\Service\Order\RequirementsPlanner;
use App\Katzen\Service\Order\PricingService;
use App\Katzen\ValueObject\PricingContext;

use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\Messenger\Message\AsyncTaskMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The primary Order domain orchestrator.
 *
 */
final class OrderService
{
  public function __construct(
    private OrderRepository $orderRepo,
    private OrderItemRepository $itemRepo,
    private RecipeRepository $recipeRepo,
    private SellableRepository $sellableRepo,
    private RequirementsPlanner $requirements,
    private AccountingService $accounting,
    private CostingService $costing,
    private PricingService $pricing,    
    private InventoryService $inventoryService,
    private StockTargetAutogenerator $autogenerator,
    private RecipeExpanderService $expander,
    private MessageBusInterface $bus,
  ) {}

  /**   
   * Create a new order and order items.
   *
   * Handles recipe lookup, COGS estimation, price defaults, and validation.
   *
   * * @param Order $order The hydrated order entity
   * * @param array $itemData [
   * *   ['sellable_id' => int, 'quantity' => int, 'unit_price' => float, 'notes' => string],
   * *   ...
   * * ]
   * @param array $options Optional configuration:
   *   - 'calculate_cogs' => bool (default: true) - Whether to estimate COGS
   *   - 'use_recipe_prices' => bool (default: false) - Override with recipe default prices - DONT USE THIS
   *   - 'apply_customer_pricing' => bool (default: false) - Apply customer-specific pricing rules
   *
   * @return ServiceResponse
   */
  public function createOrder(
    Order $order,
    array $itemsData,
    array $options = []
  ): ServiceResponse
  {
     try {
       if (empty($itemsData)) {
         return ServiceResponse::failure(
           errors: ['No items provided'],
           message: 'Order must have at least one item',
           data: ['order_id' => $order->getId()]
         );
       }
       
       $calculateCogs = $options['calculate_cogs'] ?? true;
       $useRecipePrices = $options['use_recipe_prices'] ?? false;
       $applyCustomerPricing = $options['apply_customer_pricing'] ?? false;

       $sellableIds = array_column($itemsData, 'sellable_id');
       $sellables = $this->sellableRepo->findBy(['id' => $sellableIds]);

       if (count($sellables) !== count($sellableIds)) {
         $foundIds = array_map(fn($r) => $r->getId(), $sellables);
         $missingIds = array_diff($sellableIds, $foundIds);
         
         return ServiceResponse::failure(
           errors: [sprintf('Invalid sellable IDs: %s', implode(', ', $missingIds))],
           message: 'Some items could not be found',
           data: ['order_id' => $order->getId()]
         );
       }

       $sellableMap = [];
       foreach ($sellables as $sellable) {
         $sellableMap[$sellable->getId()] = $sellable;
       }
       
       # TODO: design a Sellable or Product entity and clarify the
       #       Order, Menu, Recipe, Item, StockTarget associations
       $errors = [];
       foreach ($itemsData as $itemData) {
         $sellableId = $itemData['sellable_id'];
         $sellable = $sellableMap[$sellableId];

         $quantity = (int)($itemData['quantity'] ?? 1);
         if ($quantity < 1) {
           $errors[] = sprintf('Invalid quantity for sellable %s', $sellable->getName());
           continue;
         }

         # TODO: initialize OrderItem Price from pricing service
         # TODO: design OrderItem level tax rules          
         $unitPrice = $this->resolveUnitPrice(
           $itemData,
           $sellable,
           $order->getCustomerEntity(),
           $useRecipePrices,
           $applyCustomerPricing
         );

         $cogs = 0.00;
         if ($calculateCogs) {
           try {
             # TODO: a more elegant costing, for a more elegant Product Catalog and Pricing model
#             $cogs = $this->costing->getRecipeCost($recipe, $quantity);
             $item_cogs = 0.00;
             foreach ($sellable->getComponents() as $component) {
               $item_cogs += $this->costing->getInventoryCost(
                 $component->getTarget(),
                 $component->getQuantityMultiplier() * $quantity,
               );
             }
             $cogs += $item_cogs;
             
           } catch (\Exception $e) {
             // Soft fail on COGS issues
             $errors[] = sprintf(
               'Could not estimate COGS for %s: %s',
               $sellable->getName(),
               $e->getMessage()
             );
           }
         }

         $orderItem = new OrderItem();
         $orderItem->setSellable($sellable);
         $orderItem->setQuantity($quantity);
         $orderItem->setUnitPrice($unitPrice);
         $orderItem->setCogs($cogs);
         
         if (!empty($itemData['notes'])) {
           $orderItem->setNotes($itemData['notes']);
         }
         
         $order->addOrderItem($orderItem);
       }

       $order->calculateTotals();

       $this->orderRepo->save($order, true);

       $response = ServiceResponse::success(
         data: [
           'order_id' => $order->getId(),
           'item_count' => count($order->getOrderItems()),
           'subtotal' => $order->getSubtotal(),
           'tax_amount' => $order->getTaxAmount(),
           'total_amount' => $order->getTotalAmount(),
         ],
         message: 'Order created successfully'
       );
       
       if (!empty($errors)) {
         return ServiceResponse::success(
           data: $response->getData(),
           message: $response->getMessage(),
           metadata: ['warnings' => $errors]
         );
       }

       return $response;
      
     } catch (\Throwable $e) {
       return ServiceResponse::failure(
         errors: [$e->getMessage()],
         message: 'Failed to create order',
         data: ['order_id' => $order->getId()],
         metadata: [
           'exception' => get_class($e),
           'code'      => (int) $e->getCode(),
         ]
       );
     }
  }  

  /**
   * Update an existing order with new items
   * 
   * Replaces all order items with new data. Validates that order can be modified.
   *
   * @param Order $order The order to update
   * @param array $itemsData Same format as createOrder()
   * @param array $options Same options as createOrder()
   * @return ServiceResponse
   */
  public function updateOrder(Order $order, array $itemsData, array $options): ServiceResponse
  {
    try {
      if (!$order->canBeModified()) {
        return ServiceResponse::failure(
          errors: ['Order cannot be modified in its current state'],
          message: sprintf('Cannot edit order in %s status', $order->getStatus()),
          data: ['order_id' => $order->getId()]
        );
      }

      if (empty($itemsData)) {
        return ServiceResponse::failure(
          errors: ['No items provided'],
          message: 'Order must have at least one item'
        );
      }

      foreach ($order->getOrderItems()->toArray() as $item) {
        $order->removeOrderItem($item);
      }
      
      $result = $this->buildOrderItems($order, $itemsData, $options);
            
      if ($result->isFailure()) {
        return $result;
      }

      $this->orderRepo->save($order, true);
      
      return ServiceResponse::success(
        data: [
          'order_id' => $order->getId(),
          'item_count' => count($order->getOrderItems()),
          'subtotal' => $order->getSubtotal(),
          'tax_amount' => $order->getTaxAmount(),
          'total_amount' => $order->getTotalAmount(),
        ],
        message: 'Order updated successfully'
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to update order',
        metadata: [
          'exception' => get_class($e),
          'order_id' => $order->getId(),
        ]
      );
    }
  }

  /**
   * Build OrderItem entities and add to order
   * 
   * Extracted for reuse between create and update
   */
  private function buildOrderItems(Order $order, array $itemsData, array $options): ServiceResponse
  {
    $calculateCogs = $options['calculate_cogs'] ?? true;
    $useRecipePrices = $options['use_recipe_prices'] ?? false;
    $applyCustomerPricing = $options['apply_customer_pricing'] ?? false;
    
    $sellableIds = array_column($itemsData, 'sellable_id');
    $sellables = $this->sellableRepo->findBy(['id' => $sellableIds]);
    
    if (count($sellables) !== count($sellableIds)) {
      $foundIds = array_map(fn($r) => $r->getId(), $sellables);
      $missingIds = array_diff($sellableIds, $foundIds);
      
      return ServiceResponse::failure(
        errors: [sprintf('Invalid sellable IDs: %s', implode(', ', $missingIds))],
        message: 'Some items could not be found',
        metadata: [
          'order_id' => $order->getId(),
        ]
      );
    }

    $sellableMap = [];
    foreach ($sellables as $sellable) {
      $sellableMap[$sellable->getId()] = $sellable;
    }

    $errors = [];
    foreach ($itemsData as $itemData) {
      $sellableId = $itemData['sellable_id'];
      $sellable = $sellableMap[$sellableId];
      
      $quantity = (int)($itemData['quantity'] ?? 1);
      if ($quantity < 1) {
        $errors[] = sprintf('Invalid quantity for sellable %s', $sellable->getName());
        continue;
      }
      
      $unitPrice = $this->resolveUnitPrice(
        $itemData,
        $sellable,
        $order->getCustomerEntity(),
        $useRecipePrices,
        $applyCustomerPricing
      );

      $cogs = 0.00;
      if ($calculateCogs) {
        try {
          # TODO: a more elegant costing, for a more elegant Product Catalog and Pricing model
          $item_cogs = 0.00;
          foreach ($sellable->getComponents() as $component) {
            $item_cogs += $this->costing->getInventoryCost(
              $component->getTarget(),
              $component->getQuantityMultiplier() * $quantity,
            );
          }
          $cogs += $item_cogs;
        } catch (\Exception $e) {
          $errors[] = sprintf(
            'Could not estimate COGS for %s: %s',
            $sellable->getName(),
            $e->getMessage(),
          );
        }
      }

      $orderItem = new OrderItem();
      $orderItem->setSellable($sellable);
      $orderItem->setQuantity($quantity);
      $orderItem->setUnitPrice($unitPrice);
      $orderItem->setCogs($cogs);
      
      if (!empty($itemData['notes'])) {
        $orderItem->setNotes($itemData['notes']);
      }
      
      $order->addOrderItem($orderItem);
    }
    
    if (!empty($errors)) {
      return ServiceResponse::success(
        data: [],
        message: 'Items added with warnings',
        metadata: ['warnings' => $errors]
      );
    }
    
    return ServiceResponse::success(data: []);
  }

  /**
   * Resolve the unit price for an order item
   * 
   * Priority:
   * 1. Explicit price from form data (user override)
   * 2. Customer-specific pricing (if enabled)
   * 3. Recipe default price (if enabled)
   * 4. Fallback to 0.00
   */
  private function resolveUnitPrice(
    array $itemData,
    Sellable $sellable,
    $customer,
    bool $useRecipePrices,
    bool $applyCustomerPricing
  ): string {
    if (isset($itemData['unit_price'])) {
      return number_format((float)$itemData['unit_price'], 2, '.', '');
    }
    
    if ($applyCustomerPricing && $customer) {
      // TODO: Implement customer pricing lookup
      // $customPrice = $this->pricingService->getPriceForCustomer($customer, $recipe);
      // if ($customPrice) return $customPrice;
    }

    if ($useRecipePrices && $sellable->getBasePrice()) {
      return $recipe->getBasePrice();
    }

    return '0.00';
  }

  /**
   * Close an order, enqueue inventory consumption and accounting events.
   *
   * Runs requirement planning to compute stock consumption per StockTarget and
   * enqueues asynchronous `consume_stock` tasks for each requirement.
   * If planning yields errors or invalid quantities/targets, returns a failure
   * with warnings and does not close the order.
   * On success, enqueues the async  `unbilled_ar_on_fulfillment` journal event
   * and marks the order `closed`.
   *
   * * @param Order $order The order to close
   * * @return ServiceResponse Returns success with task counts or failure with details
   */
  public function closeOrder(Order $order): ServiceResponse
  {
    try {
      if ($order->isClosed()) {
        return ServiceResponse::success(
          data: [
            'order_id'       => $order->getId(),
            'enqueued_tasks' => 0,
            'status'         => 'closed',
          ],
          message: 'Order is already marked close; no work performed.'
        );
      }
      
      $warnings      = [];
      $enqueuedTasks = 0;
      
      $plan = $this->requirements->plan(['orders'=>[$order]], purpose: 'consume', groupBy: 'stockTarget');

      if ($plan->isFailure()) {
        return ServiceResponse::failure(
          errors: $plan->getErrors(),
          data: $plan->getData(),
          message: $plan->getMesssage(),
          metadata: [
            'order_id' => $order->getId()
          ]
        );
      }
      
      foreach ($plan->getData()['requirements'] as $target_id => $qty) {
        if ($qty <= 0) {
          $warnings[] = 'No stock consumption recorded for order item: invalid quantity.';
          continue;
        }       
        
#        $this->mq->send(new AsyncTaskMessage(
        $this->bus->dispatch(new AsyncTaskMessage(
          taskType: 'consume_stock',
          payload: [
            'stock_target.id' => $target_id,
            'quantity' => $qty,
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
          message: 'Order not closed due to errors.',
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

      $order->fulfillAll(); # TODO: not this
      $order->close();
      $this->orderRepo->save($order);
      
      return ServiceResponse::success(
        data: [
          'order_id'       => $order->getId(),
          'enqueued_tasks' => $enqueuedTasks,
          'status'         => 'closed',
        ],
        message: 'Order complete!',
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to close order: ' . $e->getMessage()],
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
  
  /**
   * Check inventory coverage for all open orders.
   *
   * Retrieves pending orders, aggregates required quantities by StockTarget, and
   * delegates a bulk stock check to the InventoryService.
   *
   * * @return ServiceResponse Returns stock check results for all open orders
   */
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

  /**
   * Aggregate stock requirements for a set of orders.
   *
   * Expands each order item’s recipe into stock consumptions using the RecipeExpander,
   * then sums required base quantities by StockTarget ID. Collects non-blocking
   * per-item errors (missing targets/quantities) in `metadata.errors`.
   *
   * * @param Order[] $orders List of orders to analyze
   * * @return ServiceResponse On success: data['requirements'] = array<int,float> (targetId => qty)
   */
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
