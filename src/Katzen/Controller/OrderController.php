<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Tag;
use App\Katzen\Form\OrderType;
use App\Katzen\Repository\RecipeListRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\TagRepository;
use App\Katzen\Service\DashboardContextService;
use App\Katzen\Service\DefaultMenuPlanner;
use App\Katzen\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
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
  ) {}
  
  #[Route('/orders', name: 'order_index')]
  public function index(Request $request, EntityManagerInterface $em): Response  
  {
    # TODO: Add ['created_at' => 'DESC']) to Order entity
    $orders = $em->getRepository(Order::class)->findBy([
      'status' => 'pending'
    ]); 
    return $this->render('katzen/order/list_orders.html.twig', $this->dashboardContext->with([
      'activeItem' => 'orders',                    
      'activeMenu' => null,
      'orders' => $orders,
    ]));
  }

  #[Route('/order/create', name: 'order_create_form')]
  public function orderCreate(Request $request, EntityManagerInterface $em, RecipeListRepository $recipeRepo, TagRepository $tagRepo): Response
  {
    $order = new Order();
    $form = $this->createForm(OrderType::class, $order);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $order = $form->getData();

      $recipeIdsCsv = $form->get('recipeIds')->getData();
      $recipeIds = array_filter(array_map('intval', explode(',', $recipeIdsCsv)));

      $this->orderService->createOrder($order, $recipeIds);
      
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
  public function orderEdit(int $id, Request $request, EntityManagerInterface $em): Response
  {
    $order = $em->getRepository(Order::class)->find($id);
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
  
  #[Route('/order/complete/{id}', name: 'order_mark_ready')]
  public function orderMarkReady(int $id, Request $request, EntityManagerInterface $em): Response
  {
    $order = $em->getRepository(Order::class)->find($id);
    if (!$order) {
      throw $this->createNotFoundException('Order not found.');
    }

    $this->orderService->completeOrder($order);
    
    $this->addFlash('success', 'Order created!');
    return $this->redirectToRoute('order_index');
  }

  #[Route('/api/orders/{id}/schedule', name: 'order_schedule_update', methods: ['PATCH'])]
  public function updateSchedule(int $id, Request $req, EntityManagerInterface $em): Response
  {
    $order = $em->getRepository(Order::class)->find($id);
    if (!$order) { return $this->json(['error' => 'not found'], 404); }
    
    $data = json_decode($req->getContent(), true) ?? [];
    if (isset($data['scheduledDate'])) {
      $order->setScheduledDate(new \DateTimeImmutable($data['scheduledDate']));
    }
    if (isset($data['slot'])) { $order->setSlot($data['slot']); }
    
    $em->flush();
    return $this->json(['ok' => true]);
  }
}
