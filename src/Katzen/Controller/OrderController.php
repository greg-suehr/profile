<?php

namespace App\Katzen\Controller;

use App\Katzen\Repository\RecipeListRepository;
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
  public function index(): Response
  {
    return $this->render('katzen/order/list_orders.html.twig', $this->dashboardContext->with([
      'activeItem' => 'orders',                    
      'activeMenu' => null,
      'orders' => [],
    ]));
    }

  #[Route('/order/create', name: 'order_create_form')]
  public function orderCreate(Request $request, EntityManagerInterface $em, RecipeListRepository $recipeRepo, TagRepository $tagRepo): Response
  {
    return $this->render('katzen/manager/base.html.twig', $this->dashboardContext->with([
        'activeItem' => 'order-create',
        'activeMenu' => 'order',
    ]));
  }

  #[Route('/order/edit/{id}', name: 'menu_edit_form')]
  public function orderEdit(int $id, Request $request, EntityManagerInterface $em): Response
  {
    return $this->render('katzen/manager/base.html.twig', $this->dashboardContext->with([
      'activeItem' => 'order-create',
      'activeMenu' => 'order',
    ]));
  }
}
