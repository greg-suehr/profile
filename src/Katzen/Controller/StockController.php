<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\PanelView\{PanelView, PanelCard, PanelField, PanelGroup, PanelAction};
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
    private StockTargetRepository $stockRepo,
  ) {}
  
  #[Route('/stock', name: 'stock_index')]
  public function index(Request $request): Response
  {
    $activeGroup = $request->query->get('group');
    $q = $request->query->get('q');
    $targets = $this->stockRepo->findBy([]);

    $cards = [];
    foreach ($targets as $t) {
      $data = [
        'id'            => (string) $t->getId(),
        'name'          => $t->getName(),                           // e.g. "Rice"
        'item_name'     => $t->getItem()?->getName(),
        'source_type'   => $t->getItem() ? 'item' : 'recipe',
      #  'location'      => $t->getLocation()?->getLabel() ?? null,
        'qty'           => $t->getCurrentQty(),                       // float
        'unit'          => $t->getBaseUnit()?->getAbbreviation() ?? null,     // "g", "kg", etc.
      #  'min_qty'       => $t->getMinQuantity() ?? 0,
        'status'        => $t->getCurrentQty() <= 0 ? 'out'
          : ($t->getCurrentQty() <= ($t->getReorderPoint() ?? 0) ? 'low' : 'ok'),
      #  'last_count'    => $t->getLastCountedAt(),
      #  'updated_at'    => $t->getUpdatedAt(),
      ];

      $card = PanelCard::create($data['id'])
                ->setTitle($data['name'] ?? $data['item_name'] ?? 'Untitled')
                ->setData($data)
                ->addBadge(strtoupper($data['status']), match ($data['status']) {
                    'out' => 'danger', 'low' => 'warning', default => 'success'
                  })
#                ->setMeta($data['location'] ? "Location: {$data['location']}" : null)
                ->addPrimaryField(PanelField::number('qty', 'Quantity', 2)->icon('bi-box'))
                ->addPrimaryField(PanelField::text('unit', 'Unit')->muted())
#                ->addContextField(PanelField::date('last_count', 'Last Count', 'Y-m-d'))
#                ->addContextField(PanelField::date('updated_at', 'Updated', 'Y-m-d H:i'))
                ->addQuickAction(
                  PanelAction::view([ 'name' => 'stock_target_view', 'params' => ['id' => $data['id']]])
                )
#                ->addQuickAction(
#                  PanelAction::edit([ 'name' => 'stock_target_edit', 'params' => ['id' => $data['id']]])
#                )
                ->addQuickAction(
                  PanelAction::create('count', 'Count Now')
                        ->setIcon('bi-clipboard-check')
                        ->setVariant('outline-success')
                        ->setRoute([ 'name' => 'stock_count_create', 'params' => ['ids' => $data['id']]])
                );

      if ($data['status'] === 'out') { $card->setBorderColor('var(--color-error)'); }
      elseif ($data['status'] === 'low') { $card->setBorderColor('var(--color-warning)'); }
      
      $cards[] = $card;
    }

    $groups = [
      PanelGroup::create('all', 'All')->setIcon('bi-grid'),
      PanelGroup::create('low', 'Low Stock')->whereEquals('status', 'low')->setIcon('bi-exclamation-triangle'),
      PanelGroup::create('out', 'Out of Stock')->whereEquals('status', 'out')->setIcon('bi-x-octagon'),
      PanelGroup::create('items_only', 'Items Only')->whereEquals('source_type', 'item')->setIcon('bi-bag'),
      PanelGroup::create('prep_only', 'Prep Only')->whereEquals('source_type', 'recipe')->setIcon('bi-egg-fried')
       ];

    $panel = PanelView::create('stock')
            ->setCards($cards)
            ->setSelectable(true)
            ->setSearchPlaceholder('Search name, location, unit…')
            ->setEmptyState('No inventory targets found.')
            ->addBulkAction(
              PanelAction::create('bulk_count', 'Start Count Session')->setIcon('bi-clipboard')->setVariant('outline-primary')
            )
            ->addBulkAction(
              PanelAction::create('bulk_adjust', 'Adjust Quantities')->setIcon('bi-arrow-left-right')->setVariant('outline-secondary')
            )
            ->addBulkAction(
              PanelAction::delete([ 'name' => 'stock_target_bulk_archive'])
            );

    foreach ($groups as $g) { $panel->addGroup($g); }

    if (\in_array($activeGroup, array_map(fn($g) => $g->getKey(), $groups), true)) {
      $panel->setActiveGroup($activeGroup === 'all' ? null : $activeGroup);
    }
    
    $view = $panel->build();
    
    return $this->render('katzen/component/panel_view.html.twig', $this->dashboardContext->with([
      'activeMenu' => 'stock',
      'activeItem' => 'stock',
      'view' => $view,
      'q'    => $q,
      'activeGroup' => $activeGroup ?? 'all',
      'groupSlug' => 'stock_index',
    ]));
  }

  #[Route('/stock/bulk', name: 'stock_bulk', methods: ['POST'])]
  public function stockBulk(Request $request): Response
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
        $target = $this->stockRepo->findBy([ 'id' => $id]);
        if ($target) $this->stockRepo->delete($target);
      }
      break;

    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }

    return $this->json(['ok' => true]);
  }

  #[Route('stock/manage', name: 'stock_table')]
  public function manage(Request $request): Response
  {
    $targets = $this->stockRepo->findBy([]);
        
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
            ->addQuickAction(TableAction::view('stock_target_view'))
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
  ): Response
  {
    if ($ids === null) {
      $targets = $this->stockRepo->findBy([]);
    }
    else {
      $targets = $this->stockRepo->findByIds(explode(',',$ids));
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

  #[Route('/stock/show/{id}', name: 'stock_target_view')]
  public function stock_target_view(
    Request $request,
    int $id,
  ) : Response
  {
    $target = $this->stockRepo->find($id);
    
    if (!$target) {
        throw $this->createNotFoundException("StockTarget #$id not found.");
    }

    if ($target->getItem()) {
        return $this->redirectToRoute('item_show', [
            'id' => $target->getItem()->getId()
        ]);
    }

    if ($target->getRecipe()) {
        return $this->redirectToRoute('recipe_view', [
            'id' => $target->getRecipe()->getId()
        ]);
    }

    $this->addFlash('warning', 'This stock target has no associated item or recipe.');
    return $this->redirectToRoute('stock_index');
  }

  #[Route('/stock/adjust/{id}', name: 'stock_target_adjust')]
  public function stock_target_adjust(
    Request $request,
    int $id,
  ) : Response
  {
    $target = $this->stockRepo->find($id);

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
