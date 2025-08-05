<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Service\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class StockController extends AbstractController
{
  public function __construct(private DashboardContextService $dashboardContext) {}
  
  #[Route('/stock', name: 'stock_index')]
  public function index(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);
    return $this->render('katzen/stock/_list_stock.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => null,
      'targets'    => $targets,
    ]));
  }

  #[Route('/stock/count', name: 'stock_count_create')]
  public function stock_count_create(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);
    return $this->render('katzen/stock/_list_stock.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => null,
      'targets'    => $targets,
    ]));
  }

  #[Route('/stock/show', name: 'stock_target_show')]
  public function stock_target_show(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);
    return $this->render('katzen/stock/_list_stock.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => null,
      'targets'    => $targets,
    ]));
  }

    #[Route('/stock/adjust', name: 'stock_target_adjust')]
  public function stock_target_adjust(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);
    return $this->render('katzen/stock/_list_stock.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => null,
      'targets'    => $targets,
    ]));
  }  
}
