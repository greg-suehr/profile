<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\PanelView\{PanelView, PanelCard, PanelField, PanelGroup, PanelAction};
use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Tag;
use App\Katzen\Form\OrderType;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\TagRepository;
use App\Katzen\Service\Order\DefaultMenuPlanner;
use App\Katzen\Service\OrderService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class OrderController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private DefaultMenuPlanner $menuPlanner,
    private OrderService $orderService,
    private OrderRepository $orderRepo,
    private TagRepository $tagRepo,    
  ) {}
  
  #[Route('/orders', name: 'order_index')]
  public function index(Request $request): Response  
  {
    $activeGroup = $request->query->get('group');
    $q = $request->query->get('q');
    # TODO: Add ['created_at' => 'DESC']) to Order entity
    $orders = $this->orderRepo->findBy([
      'status' => ['pending', 'ready', 'waiting'],
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
        ->addBadge(strtoupper($data['status']), match ($data['status']) {
            'waiting' => 'danger', 'ready' => 'success', default => 'warning'
          })
        ->addPrimaryField(PanelField::number('numItems', 'Items', 0)->icon('bi-box'))
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
      
      if ($data['status'] === 'waiting') { $card->setBorderColor('var(--color-error)'); }

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
      'activeItem' => 'orders',
      'activeMenu' => null,
      'view' => $view,
      'q'    => $q,
      'activeGroup' => $activeGroup ?? 'all',
      'groupSlug' => 'order_index',
    ]));
  }

  #[Route('/order/create', name: 'order_create')]
  public function orderCreate(Request $request): Response
  {
    $order = new Order();
    $form = $this->createForm(OrderType::class, $order);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $order = $form->getData();

      $recipeIdsCsv = $form->get('recipeIds')->getData();
      $recipeIds = array_filter(array_map('intval', explode(',', $recipeIdsCsv)));

      $recipeQuantities = [];
        foreach ($recipeIds as $rid) {
            $recipeQuantities[$rid] = 1; # TODO: read recipeQuantities from Form
        }

      $response = $this->orderService->createOrder($order, $recipeQuantities);
      
      $this->addFlash('success', 'Order created!');
      return $this->redirectToRoute('order_index');
    }

    $menu = $this->menuPlanner->getActiveMenu();

    if (!$menu) {
      throw $this->createNotFoundException('No active menu found.');
    }

    return $this->render('katzen/order/create_order.html.twig', $this->dashboardContext->with([
        'activeItem' => 'orders',
        'activeMenu' => 'order',
        'menuInterface' => $menu,
        'form'       => $form,
        'recipes'    => $menu->getRecipes(),
    ]));
  }

  #[Route('/order/edit/{id}', name: 'order_edit_form')]
  public function orderEdit(int $id, Request $request): Response
  {
    $order = $this->orderRepo->find($id);
    if (!$order) {
      throw $this->createNotFoundException('Order not found.');
    }

    if ($order->getOrderItems()->isEmpty()) {      
      $order->addOrderItem(new OrderItem());
    }
    $form = $this->createForm(OrderType::class, $order);   
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $order = $form->getData();
      
      $recipeIdsCsv = $form->get('recipeIds')->getData();
      $recipeIds = array_filter(array_map('intval', explode(',', $recipeIdsCsv)));

      $this->orderService->updateOrder($order, $recipeIds);
      
      $this->addFlash('success', 'Order updated!');
      return $this->redirectToRoute('order_index');
    }

    $menu = $this->menuPlanner->getActiveMenu();
    
    if (!$menu) {
      throw $this->createNotFoundException('No active menu found.');
    }

    $existingRecipeIds = [];
    foreach ($order->getOrderItems() as $item) {
      $existingRecipeIds[] = $item->getRecipeListRecipeId()->getId();
    }

    return $this->render('katzen/order/create_order.html.twig', $this->dashboardContext->with([
      'activeItem' => 'orders',
      'activeMenu' => 'order',
      'menuInterface' => $menu,
      'form'       => $form,
      'recipes'    => $menu->getRecipes(),
      'selectedRecipeIds' => implode(',', $existingRecipeIds),
    ]));
  }
  
  #[Route('/order/complete/{id}', name: 'order_complete', methods: ['POST'])]
  public function orderMarkReady(Order $order, Request $request): Response
  {
    # TODO: test and fix CSRF
    $this->denyAccessUnlessGranted('ROLE_USER');
    if (!$this->isCsrfTokenValid('order_complete_'.$order->getId(), (string) $request->request->get('_token'))) {
      $this->addFlash('danger', 'Invalid request token.');
      return $this->redirectToRoute('order_index');
    }
    
    $response = $this->orderService->completeOrder($order);

    if ($response->isSuccess()) {
      $msg = $response->message ?? 'Order complete!';
      $status = $response->getData()['status'] ?? 'complete';
      $this->addFlash($status === 'already_complete' ? 'info' : 'success', $msg);
      return $this->redirectToRoute('order_index');
    }

    $msg = $response->message ?? 'Unable to mark order complete.';
    $first = $response->getFirstError();
    $this->addFlash('danger', $first ? $msg.' '.$first : $msg);
    
    return $this->redirectToRoute('order_index');
  }

  #[Route('/api/orders/{id}/schedule', name: 'order_schedule_update', methods: ['PATCH'])]
  public function updateSchedule(int $id, Request $req): Response
  {
    $order = $this->orderRepo->findBy([ 'id' => $id ]);
    if (!$order) { return $this->json(['error' => 'not found'], 404); }
    
    $data = json_decode($req->getContent(), true) ?? [];
    if (isset($data['scheduledDate'])) {
      $order->setScheduledDate(new \DateTimeImmutable($data['scheduledDate']));
    }
    if (isset($data['slot'])) { $order->setSlot($data['slot']); }
    
    $this->orderRepo->save($order);
    return $this->json(['ok' => true]);
  }

  #[Route('/api/orders/stock_check', name: 'order_stock_check', methods: ['GET'])]
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
