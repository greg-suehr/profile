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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class OrderController extends AbstractController
{
  public function __construct(private DashboardContextService $dashboardContext) {}
  
  #[Route('/orders', name: 'order_index')]
  public function index(Request $request, EntityManagerInterface $em): Response
  {
    $orders = $em->getRepository(Order::class)->findBy([]); # TODO: Add ['created_at' => 'DESC']) to Order entity
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

      if ($recipeIds) {
        $recipes = $em->getRepository(Recipe::class)
                      ->findBy(['id' => $recipeIds]);
        
        foreach ($recipes as $recipe) {
          $item = new OrderItem();
          $item->setRecipeListRecipeId($recipe);
          $item->setQuantity(1); # TODO: parse multiple serving quantities / order sizes
          $item->setOrderId($order);
          $order->addOrderItem($item);
        }
      }

      $order->setStatus('pending');
      $em->persist($order);
      $em->flush();
      
      $this->addFlash('success', 'Order created!');
      return $this->redirectToRoute('order_index');
    }
    
    # TODO: source active RecipeLists from MenuPlanner interface
    $menu = $em->createQueryBuilder()
	    ->select('r')
        ->from(RecipeList::class, 'r')
        ->leftJoin(Tag::class, 't', 'WITH', 't.obj = :obj AND t.obj_id = r.id')
        ->setParameter('obj', 'recipe_list')
        ->andWhere('t.type = :type AND t.value IN (:status)')
        ->setParameter('type', 'menu')
        ->setParameter('status', ['current'])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

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

      $existingItems = $order->getOrderItems();
      $existingRecipeIds = [];

      foreach ($existingItems as $item) {
        $existingId = $item->getRecipeListRecipeId()->getId();
        if (!in_array($existingId, $recipeIds)) {
          $order->removeOrderItem($item);
          $em->remove($item);
        } else {
          $existingRecipeIds[] = $existingId;
        }
      }
      
      $newRecipeIds = array_diff($recipeIds, $existingRecipeIds);
      if (!empty($newRecipeIds)) {
        $newRecipes = $em->getRepository(Recipe::class)->findBy(['id' => $newRecipeIds]);

        foreach ($newRecipes as $recipe) {
          $item = new OrderItem();
          $item->setRecipeListRecipeId($recipe);
          $item->setQuantity(1);          
          $item->setOrderId($order);
          $order->addOrderItem($item);
        }
      }
      
      $order->setStatus('pending');
      $em->persist($order);
      $em->flush();
      
      $this->addFlash('success', 'Order updated!');
      return $this->redirectToRoute('order_index');
    }
    
    # TODO: source active RecipeLists from MenuPlanner interface
    $menu = $em->createQueryBuilder()
        ->select('r')
        ->from(RecipeList::class, 'r')
        ->leftJoin(Tag::class, 't', 'WITH', 't.obj = :obj AND t.obj_id = r.id')
    	->setParameter('obj', 'recipe_list')
        ->andWhere('t.type = :type AND t.value IN (:status)')
        ->setParameter('type', 'menu')
        ->setParameter('status', ['current'])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    
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
}
