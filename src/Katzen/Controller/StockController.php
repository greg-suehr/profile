<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableFilter, TableAction};
use App\Katzen\Entity\StockCount;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Recipe;
use App\Katzen\Form\StockAdjustType;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Service\InventoryService;
use App\Katzen\Service\Utility\DashboardContextService;
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

  #[Route('/stock/bulk', name: 'stock_bulk', methods: ['POST'])]
  public function stockBulk(Request $request, EntityManagerInterface $em): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('stock_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids    = array_map('intval', $payload['ids'] ?? []);

    if (!$action || empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
    }
    
    switch ($action) {
    case 'count':
      return $this->json(['ok' => true, 'redirect' => $this->generateUrl('stock_count_create', [ 'ids' => implode(',',array_values($ids))] )]);
    case 'delete':
      foreach ($ids as $id) {
        $target = $em->getRepository(StockTarget::class)->find($id);
        if ($target) $em->remove($target);
      }
      $em->flush();
      break;

    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }

    return $this->json(['ok' => true]);
  }

  #[Route('stock/manage', name: 'stock_table')]
  public function manage(Request $request, EntityManagerInterface $em): Response
    {
        $targets = $em->getRepository(StockTarget::class)->findBy([]);
        
        $rows = [];
        foreach ($targets as $target) {
          $sourceName = '';
          if ($target->getItem()) {
            $sourceName = 'Item: ' . $target->getItem()->getName();
          } elseif ($target->getRecipe()) {
            $sourceName = 'Recipe: ' . $target->getRecipe()->getTitle();
          }
          
          $row = TableRow::create([
            'name' => $target->getName(),
            'status' => $target->getStatus() ?? 'OK',
            'available' => $target->getCurrentQty() . ' ' . ($target->getBaseUnit()?->getName() ?? 'units'),
            'source' => $sourceName,
          ])
          ->setId($target->getId());

          $status = $target->getStatus();
          if ($status === 'Out') {
            $row->setStyleClass('table-danger');
          } elseif ($status === 'Low') {
            $row->setStyleClass('table-warning');
          }$rows[] = $row;
        }

        $table = TableView::create('stock-table')
            ->addField(
              TableField::text('name', 'Item Name')
                    ->sortable()
            )
            ->addField(
              TableField::badge('status', 'Status')
                    ->badgeMap([
                      'OK' => 'success',
                      'Low' => 'warning',
                      'Out' => 'danger',
                    ])
                    ->sortable()
            )
            ->addField(
            TableField::text('available', 'Available')
                    ->align('right')
                    ->sortable()
            )
            ->addField(
              TableField::text('source', 'Source')
                    ->hiddenMobile()
            )
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(TableAction::view()->setRoute('stock_target_show'))
            ->addQuickAction(
              TableAction::create('adjust', 'Adjust')
                    ->setIcon('bi-plus-slash-minus')
                    ->setVariant('outline-secondary')
                    ->setRoute('stock_target_adjust')
            )
            ->addBulkAction(
              TableAction::create('count', 'Start Count for Selected')
                    ->setIcon('bi-clipboard-check')
                    ->setVariant('outline-primary')
            )
            ->setSearchPlaceholder('Type item names, comma-separated (e.g. "blueberry, pie dough, salt, sugar")')
            ->setEmptyState('No stock targets configured.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
          'activeItem' => 'items',
          'activeMenu' => 'stock',
          'table' => $table,
          'bulkRoute' => 'stock_bulk',
          'csrfSlug' => 'stock_bulk',
        ]));
   } 
  
  #[Route('/stock/count/{ids?}', name: 'stock_count_create')]
  public function stock_count_create(
    ?string $ids,
    Request $request,
    EntityManagerInterface $em
  ): Response
  {
    if ($ids === null) {
      $targets = $em->getRepository(StockTarget::class)->findBy([]);
    }
    else {
      $targets = $em->getRepository(StockTarget::class)->findByIds(explode(',',$ids));
    }

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
      
      $result = $this->inventoryService->recordBulkStockCount($inputCounts, $notes);

      if ($result->isFailure()) {
        $this->addFlash('danger', $result->getMessage() ?: 'Failed to record stock count.');
        return $this->redirectToRoute('stock_index');        
      }

      $data = $result->getData();
      $this->addFlash('success', sprintf(
        'Stock count recorded for %d items.',
        (int)($data['item_count'] ?? 0)
      ));
      
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
      $result = $this->inventoryService->adjustStock($target->getId(), $data['qty'], $data['reason'] ?? null);

      if ($result->isFailure()) {
        $this->addFlash('danger', $result->getMessage() ?: 'Failed to adjust stock.');        
        $this->addFlash('warning', implode('; ', (array)$result->getErrors()));
        return $this->redirectToRoute('stock_index');
      }
      
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
