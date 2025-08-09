<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\StockCount;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Recipe;
use App\Katzen\Form\StockAdjustType;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Service\DashboardContextService;
use App\Katzen\Service\InventoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class StockController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private InventoryService $inventoryService,
  ) {}
  
  #[Route('/stock', name: 'stock_index')]
  public function index(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);
    return $this->render('katzen/stock/_list_stock.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => 'stock',
      'targets'    => $targets,
    ]));
  }

  #[Route('/stock/count', name: 'stock_count_create')]
  public function stock_count_create(Request $request, EntityManagerInterface $em): Response
  {
    $targets = $em->getRepository(StockTarget::class)->findBy([]);

    if ($request->isMethod('POST')) {
      $notes = $request->request->get('notes') ?? '';
      $inputCounts = [];
      
      foreach ($targets as $target) {
        $field = 'counted_' . $target->getId();
        $qty = $request->request->get($field);
        if ($qty !== null && $qty !== '') {
          $inputCounts[$target->getId()] = (float)$qty;
        }
      }
      
      $this->inventoryService->recordBulkStockCount($inputCounts, $notes);
      
      $this->addFlash('success', 'Stock count recorded.');
      return $this->redirectToRoute('stock_index');
    }

    return $this->render('katzen/stock/_bulk_count.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => 'stock',
      'targets'    => $targets,
    ]));
  }

  #[Route('/stock/show/{id}', name: 'stock_target_show')]
  public function stock_target_show(
    Request $request,
    EntityManagerInterface $em,
    int $id,
  ) : Response
  {
    $target = $em->getRepository(StockTarget::class)->find($id);
    
    if (!$target) {
        throw $this->createNotFoundException("StockTarget #$id not found.");
    }

    if ($target->getItem()) {
        return $this->redirectToRoute('item_show', [
            'id' => $target->getItem()->getId()
        ]);
    }

    if ($target->getRecipe()) {
        return $this->redirectToRoute('recipe_show', [
            'id' => $target->getRecipe()->getId()
        ]);
    }

    $this->addFlash('warning', 'This stock target has no associated item or recipe.');
    return $this->redirectToRoute('stock_index');
  }

  #[Route('/stock/adjust/{id}', name: 'stock_target_adjust')]
  public function stock_target_adjust(
    Request $request,
    EntityManagerInterface $em,
    StockTargetRepository $targetRepo,
    int $id,
  ) : Response
  {
    $target = $targetRepo->find($id);

    if (!$target) {
      throw $this->createNotFoundException('Stock target not found.');
    }

    $form = $this->createForm(StockAdjustType::class, [
      'stock_target' => $target
    ]);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();
      $this->inventoryService->adjustStock($target->getId(), $data['qty'], $data['reason'] ?? null);
      
      $this->addFlash('success', 'Stock adjusted.');      
      return $this->redirectToRoute('stock_index');
    }
    
    return $this->render('katzen/stock/_adjust_stock_item.html.twig', $this->dashboardContext->with([
      'activeItem' => 'stock', 
      'activeMenu' => 'stock',
      'form' => $form->createView(),
    ]));
  }
}
