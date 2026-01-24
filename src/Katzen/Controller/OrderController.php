<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\DashboardContextService;

use App\Katzen\Component\PanelView\{PanelView, PanelCard, PanelField, PanelGroup, PanelAction};
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Form\OrderPosType;

use App\Katzen\Entity\{Order, OrderItem};
use App\Katzen\Entity\{Recipe, RecipeList, Tag};
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\SellableRepository;
use App\Katzen\Repository\TagRepository;

use App\Katzen\Service\OrderService;
use App\Katzen\Service\Order\DefaultMenuPlanner;
use App\Katzen\Service\Order\OrderActionProvider;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/order', name: 'order_', host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
final class OrderController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private DefaultMenuPlanner $menuPlanner,
    private OrderService $orderService,
    private OrderActionProvider $actionProvider,
    private OrderRepository $orderRepo,
    private SellableRepository $sellableRepo,
    private TagRepository $tagRepo,    
  ) {}
  
  #[Route('/all', name: 'index')]
  #[DashboardLayout('service', 'order', 'order-panel')] 
  public function index(Request $request): Response  
  {
    $activeGroup = $request->query->get('group');
    $q = $request->query->get('q');
    # TODO: Add ['created_at' => 'DESC']) to Order entity
    $orders = $this->orderRepo->findBy([
      'status' => ['open', 'pending', 'prep', 'ready'],
    ]);
    
    $cards = [];
    foreach ($orders as $o) {
      $data = [
        'id'            => (string) $o->getId(),
        'customer'      => $o->getCustomer(),
        'numItems'      => $o->getOrderItems()->count(),
        'status'        => $o->getStatus(),
      ];

      $card = PanelCard::create($data['id'])
        ->setTitle($data['customer'] ?? $data['customer'] ?? sprintf('Order #%d', $data['id']))
        ->setData($data)
        ->addBadge($o->getStatusLabel(), $o->getStatusBadgeClass())
        ->addPrimaryField(PanelField::number('numItems', 'Items', 0)->icon('bi-box'));

      $items = [];
      foreach ($o->getOrderItems() as $item) {
        $i = PanelField::text(
          'item_' . $item->getId(),
          ($item->isFulfilled() ? '✓ ' : '○ ') . 
            $item->getSellable()->getName() .
            ' x' . $item->getQuantity()
            );

        $items[] = $i;
        $card->addContextField($i);
      }
      # TODO: implement a more explicit field grouping for no-template PanelCards, like:
      # $card->addFieldGroup(
      #  PanelGroup::create('items', 'Items')->addFields($items)
      # );
      
      $actions = $this->actionProvider->getAvailableActions($o);

      # TODO: standardize ActionProviders - should Show be explicit?
      $card->addQuickAction(
        PanelAction::view('order_show')
      );
      
      if (isset($actions['edit'])) {
        $card->addQuickAction(
          PanelAction::edit('order_edit')
        );
      }
      
      $priority = ['open', 'mark_ready', 'fulfill_all', 'create_invoice', 'close'];
      
      foreach ($priority as $key) {        
        if (isset($actions[$key])) {
          $primaryAction = $actions[$key];

          $card->addQuickAction(
            PanelAction::custom(
              $primaryAction['route'],              
              $primaryAction['label'],
            )
            ->setRoute($primaryAction['route'])
            ->setMethod($primaryAction['method'])
            ->setIcon($primaryAction['icon'])
            ->setVariant($primaryAction['variant']));
#            ->setCsrfToken($primaryAction['csrf_token'])
#            ->setConfirm($primaryAction['confirm'])
         
          break;
        }
      }
      
      /*
        ->addQuickAction(
          PanelAction::edit([ 'name' => 'order_edit_form', 'params' => ['id' => $data['id']]])
          )
        ->addQuickAction(
          PanelAction::custom('complete', 'Mark Complete')
             ->setIcon('bi-clipboard-check')
             ->setVariant('outline-success')
             ->setMethod('POST')
             ->setRoute([ 'name' => 'order_complete', 'params' => ['id' => $data['id']]])
          );
        */      

      $cards[] = $card;
    }

    $groups = [
      PanelGroup::create('all', 'All')->setIcon('bi-grid'),
      PanelGroup::create('waiting', 'Waiting')->whereEquals('status', 'waiting')->setIcon('bi-exclamation-triangle'),
    ];

    $panel = PanelView::create('orders')
            ->setCards($cards)
            ->setSelectable(true)
            ->setSearchPlaceholder('Search by order items, customer name...')
            ->setEmptyState('No orders found.')
      ;
    
    foreach ($groups as $g) { $panel->addGroup($g); }

    if (\in_array($activeGroup, array_map(fn($g) => $g->getKey(), $groups), true)) {
      $panel->setActiveGroup($activeGroup === 'all' ? null : $activeGroup);
    }
    
    $view = $panel->build();
    
    return $this->render('katzen/component/panel_view.html.twig', $this->dashboardContext->with([
      'view' => $view,
      'q'    => $q,
      'activeGroup' => $activeGroup ?? 'all',
      'groupSlug' => 'order_index',
    ]));
  }

  #[Route('/list', name: 'table')]
  #[DashboardLayout('service', 'order', 'order-table')] 
  public function table(Request $request): Response  
  {
    $orders = $this->orderRepo->findBy([]);
    
    $rows = [];
    foreach ($orders as $o) {
       $row = TableRow::create([
         'customer' => $o->getCustomerEntity() ?  $o->getCustomerEntity()->getName() : $o->getCustomer(),
         'id'       => (string) $o->getId(),
         'numItems' => $o->getOrderItems()->count(),
         'total'    => $o->getTotalAmount(),
         'status'   => $o->getStatus(),
       ])
       ->setId($o->getId());
       
       $rows[] = $row;
    }

    $table = TableView::create('order-table')
      ->addField(
        TableField::text('customer', 'Customer Name')
          ->sortable()
          )
      ->addField(
        TableField::amount('numItems', 'Items')
          ->sortable()
          )
      ->addField(
        TableField::currency('total', 'Total')
          ->sortable()
          )
      ->addField(
        TableField::status('status', 'Status')
          )
      ->setRows($rows)
      ->setSelectable(true)
      ->addQuickAction(TableAction::view('order_show'))
      ->addBulkAction(
        TableAction::create('order_stock_check', 'Check Stock')
                    ->setIcon('bi-clipboard-check')
                    ->setVariant('outline-primary')
            )
      ->setSearchPlaceholder('Search by item, customer name (e.g. "tiramisu, ranch")')
      ->setEmptyState('No matching order.')
      ->build();

    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'bulkRoute' => 'order_bulk',
      'csrfSlug' => 'order_bulk',
    ]));
  }

  #[Route('/bulk', name: 'bulk', methods: ['POST'])]
  public function bulk(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('order_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids = array_map('intval', $payload['ids'] ?? []);
    
    if (!$action || empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
	}
    
    switch ($action) {
      case 'order_stock_check':
        return $this->orderStockCheck();
        break;
        
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
	}
    
    return $this->json(['ok' => true]);
  }

  #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
  #[DashboardLayout('service', 'order', 'order-show')]
  public function show(Order $order): Response
  {
    return $this->render('katzen/order/show_order.html.twig', $this->dashboardContext->with([
      'order' => $order,
    ]));
  }

  #[Route('/create', name: 'create')]
  #[DashboardLayout('service', 'order', 'order-create')] 
  public function create(Request $request): Response
  {
    $order = new Order();
    $form = $this->createForm(OrderPosType::class, $order);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $itemsJson = $form->get('sellableIds')->getData();
      $itemsData = json_decode($itemsJson, true);

      if (empty($itemsData)) {
        $this->addFlash('warning', 'Please add at least one item to the order');
        return $this->redirectToRoute('order_create');
      }

      $result = $this->orderService->createOrder($order, $itemsData, [
        'calculate_cogs' => true,
        'use_recipe_prices' => false,
        'apply_customer_pricing' => false,
      ]);

      if ($result->isFailure()) {
        foreach ($result->getErrors() as $error) {
          $this->addFlash('error', $error);
        }
        return $this->redirectToRoute('order_create');
      }

      $warnings = $result->getMetadata()['warnings'] ?? [];
      foreach ($warnings as $warning) {
        $this->addFlash('warning', $warning);
      }      

      $this->addFlash('success', 'Order created!');
      return $this->redirectToRoute('order_show', ['id' => $result->getData()['order_id']]);
    }

    $menu = $this->menuPlanner->getActiveMenu();

    if (!$menu) {
      throw $this->createNotFoundException('No active menu found.');
    }

    # TODO: design Catalog domain services
    $sellables = $this->sellableRepo->findBy([]);

    return $this->render('katzen/order/create_order.html.twig', $this->dashboardContext->with([
        'menuInterface' => $menu,
        'form'       => $form,
        'categories' => [], # TODO: generate categories from source Catalog
        'sellables'  => $sellables,
    ]));
  }

  #[Route('/edit/{id}', name: 'edit')]
  #[DashboardLayout('service', 'order', 'order-create')]
  public function orderEdit(int $id, Request $request): Response
  {
    $order = $this->orderRepo->find($id);
    if (!$order) {
      throw $this->createNotFoundException('Order not found.');
    }

    $form = $this->createForm(OrderPosType::class, $order);   
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $itemsJson = $form->get('sellableIds')->getData();
      $itemsData = json_decode($itemsJson, true);

      $result = $this->orderService->updateOrder($order, $itemsData, [
        'calculate_cogs' => true,
        'use_recipe_prices' => false,
        'apply_customer_pricing' => false,
      ]);

      if ($result->isFailure()) {
        foreach ($result->getErrors() as $error) {
          $this->addFlash('error', $error);
        }
        return $this->redirectToRoute('order_edit', ['id' => $id]);
      }
      
      $warnings = $result->getMetadata()['warnings'] ?? [];
      foreach ($warnings as $warning) {
        $this->addFlash('warning', $warning);
      }
      
      $this->addFlash('success', $result->getMessage());
      return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    $menu = $this->menuPlanner->getActiveMenu();
    $sellables = $this->sellableRepo->findBy([]);
    $categories = [];
    
    return $this->render('katzen/order/create_order.html.twig', $this->dashboardContext->with([
      'form' => $form,
      'order' => $order,
      'sellables' => $sellables,
      'categories' => $categories,
    ]));
  }

  #[Route('/open/{id}', name: 'open', methods: ['POST'])]
  public function open(int $id, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_open_' . $id, $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $id]);
    }

    $order = $this->orderRepo->find($id);
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }
    
    try {
      $order->open();
      $this->orderRepo->save($order);
      $this->addFlash('success', 'Order opened successfully');
    } catch (\Exception $e) {
      $this->addFlash('error', $e->getMessage());
    }
    
    return $this->redirectToRoute('order_show', ['id' => $id]);
  }

  #[Route('/ready/{id}', name: 'ready', methods: ['POST'])]
  public function markReady(int $id, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_ready_' . $id, $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $id]);
    }
    
    $order = $this->orderRepo->find($id);
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }
    
    try {
      $order->markReady();
      $this->orderRepo->save($order);
      $this->addFlash('success', 'Order marked as ready');
    } catch (\Exception $e) {
      $this->addFlash('error', $e->getMessage());
    }
    
    return $this->redirectToRoute('order_show', ['id' => $id]);
  }

  #[Route('/fulfill/{id}', name: 'fulfill_all', methods: ['POST'])]
  public function fulfillAll(int $id, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_fulfill_all_' . $id, $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $id]);
    }

    $order = $this->orderRepo->find($id);
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }
    
    try {
      $order->fulfillAll();
      $this->orderRepo->save($order);
      $this->addFlash('success', 'All items fulfilled');
    } catch (\Exception $e) {
      $this->addFlash('error', $e->getMessage());
    }
    
    return $this->redirectToRoute('order_show', ['id' => $id]);
  }

  #[Route('fulfill/{orderId}/item/{itemId}', name: 'item_fulfill', methods: ['POST'])]
  public function fulfillItem(int $orderId, int $itemId, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_item_fulfill_' . $itemId, $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $orderId]);
    }
    
    $order = $this->orderRepo->find($orderId);
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }
    
    $item = $order->getOrderItems()->filter(fn($i) => $i->getId() === $itemId)->first();
    
    if (!$item) {
      $this->addFlash('error', 'Item not found');
      return $this->redirectToRoute('order_show', ['id' => $orderId]);
    }
    
    try {
      $item->fulfill();
      $this->orderRepo->save($order);
      $this->addFlash('success', 'Item fulfilled');
    } catch (\Exception $e) {
      $this->addFlash('error', 'Failed to fulfill item');
    }
    
    return $this->redirectToRoute('order_show', ['id' => $orderId]);
  }

  #[Route('/close/{id}', name: 'close', methods: ['POST'])]
  public function close(Order $order, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_close_' . $order->getId(), $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }

    $result = $this->orderService->closeOrder($order);

    if ( $result->isFailure() ) {   
      $this->addFlash('error', $result->getMessage());
    }
    
    $this->addFlash('success', $result->getMessage());
    return $this->redirectToRoute('order_index');
  }


  #[Route('/void/{id}', name: 'void', methods: ['POST'])]
  public function void(int $id, Request $request): Response
  {
    if (!$this->isCsrfTokenValid('order_void_' . $id, $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid CSRF token');
      return $this->redirectToRoute('order_show', ['id' => $id]);
    }
    
    $order = $this->orderRepo->find($id);
    
    if (!$order) {
      throw $this->createNotFoundException('Order not found');
    }

    $reason = $request->request->get('reason');
        
    if (!$reason) {
      $this->addFlash('error', 'Void reason is required');
      return $this->redirectToRoute('order_show', ['id' => $id]);
    }
    
    try {
      $order->void($reason);
      $this->orderRepo->save($order);
      $this->addFlash('warning', 'Order voided');
    } catch (\Exception $e) {
      $this->addFlash('error', $e->getMessage());
    }
    
    return $this->redirectToRoute('order_index');
  }
  
  #[Route('/api/orders/stock_check', name: 'stock_check', methods: ['GET'])]
  public function orderStockCheck(Request $request): JsonResponse {
      $response = $this->orderService->checkStockForOpenOrders();
      
      if ($response->isFailure()) {
        return $this->json([
          'success' => false,
          'errors' => $response->getErrors(),
        ], 400);
      }

      $data = $response->getData();

      return $this->json([
        'ok' => true,
        'summary' => [
          'targets_ok' => array_filter($data,
                                       function($item) { return $item['status'] === 'ok'; }),
          'targets_insufficient' => array_filter($data,
                                                 function($item) { return $item['status'] === 'insufficient'; }),
        ],
        'targets' => $data
      ]);
  } 
}
