<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\StockLocation;
use App\Katzen\Form\StockLocationType;
use App\Katzen\Repository\StockLocationRepository;
use App\Katzen\Service\Utility\DashboardContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class StockLocationController extends AbstractController
{
    public function __construct(
      private DashboardContextService $dashboardContext,
      private StockLocationRepository $locationRepo,
      private EntityManagerInterface $em,
    ) {}
  
  #[Route('/location', name: 'location_index')]
  #[DashboardLayout('supply', 'location', 'location-table')]
  public function index(Request $request): Response
  {
    $locations = $this->locationRepo->findBy([], ['name' => 'ASC']);
        
    $rows = [];
    foreach ($locations as $location) {
      $parent = $location->getParentLocation();
            
      $rows[] = TableRow::create([
        'id' => $location->getId(),
        'code' => $location->getCode(),
        'name' => $location->getName(),
        'parent' => $parent ? $parent->getName() : '—',
        'address' => $location->getAddress() ? substr($location->getAddress(), 0, 50) . '...' : '—',
        'child_count' => $location->getChildLocations()->count(),
      ])
            ->setId($location->getId());
    }

    $table = TableView::create('Locations')
            ->addField(
              TableField::text('code', 'Code')->sortable()
            )
            ->addField(
              TableField::text('name', 'Location Name')->sortable()
            )
            ->addField(
              TableField::text('parent', 'Parent Location')->sortable()
            )
            ->addField(
              TableField::text('address', 'Address')
            )
            ->addField(
              TableField::text('child_count', 'Sub-Locations')
            )
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(
              TableAction::create('view', 'View')
                    ->setIcon('bi bi-eye')
                    ->setVariant('outline-primary')
                    ->setRoute('location_show')
            )
            ->addQuickAction(
              TableAction::create('edit', 'Edit')
                    ->setIcon('bi bi-pencil')
                    ->setVariant('outline-secondary')
                    ->setRoute('location_edit')
            )
            ->addBulkAction(
              TableAction::create('archive', 'Archive Selected')
                    ->setIcon('bi bi-archive')
                    ->setVariant('outline-warning')
                    ->setConfirmMessage('Archive selected locations?')
            )
            ->setSearchPlaceholder('Search locations by name, code, or address...')
            ->setEmptyState('No locations found. Create your first location to get started.')
            ->build();
    
    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'bulkRoute' => 'location_bulk',
      'csrfSlug' => 'location_bulk',
    ]));
  }
  
  #[Route('/location/create', name: 'location_create')]
  #[DashboardLayout('supply', 'location', 'location-create')]  
  public function create(Request $request): Response
  {
    $location = new StockLocation();
    $form = $this->createForm(StockLocationType::class, $location);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $this->em->persist($location);
      $this->em->flush();
      
      $this->addFlash('success', 'Location created successfully.');
      return $this->redirectToRoute('location_index');
    }

    return $this->render('katzen/location/create_location.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
      'location' => null,
    ]));
  }
  
  #[Route('/location/{id}', name: 'location_show', requirements: ['id' => '\d+'])]
  #[DashboardLayout('supply', 'location', 'location-show')]  
  public function show(StockLocation $location): Response
  {
        # TODO: Add detailed location view with:
        # - Current stock at this location
        # - Recent transfers
        # - Location statistics
  
    return $this->render('katzen/location/show_location.html.twig', $this->dashboardContext->with([
      'location' => $location,
    ]));
  }
  
  #[Route('/location/{id}/edit', name: 'location_edit')]
  #[DashboardLayout('supply', 'location', 'location-create')]
  public function edit(Request $request, StockLocation $location): Response
  {
    $form = $this->createForm(StockLocationType::class, $location);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $this->em->flush();
      
      $this->addFlash('success', 'Location updated successfully.');
      return $this->redirectToRoute('location_show', ['id' => $location->getId()]);
    }
    
    return $this->render('katzen/location/create_location.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
      'location' => $location,
    ]));
  }

  #[Route('/location/bulk', name: 'location_bulk', methods: ['POST'])]
  public function bulk(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    
    if (!$this->isCsrfTokenValid('location_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids = array_map('intval', $payload['ids'] ?? []);
    
    if (empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'No items selected'], 400);
    }
    
    $locations = $this->locationRepo->findBy(['id' => $ids]);
    
    switch ($action) {
    case 'archive':
      foreach ($locations as $location) {
        // TODO: Implement archiving logic
        // Check if location has active stock, prevent deletion if so
      }
      $this->em->flush();
      return $this->json([
        'ok' => true,
        'message' => count($locations) . ' location(s) archived',
        'redirect' => $this->generateUrl('location_index')
                ]);
      
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
  }
}
