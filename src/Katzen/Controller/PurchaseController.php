<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Purchase;
use App\Katzen\Form\PurchaseType;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\VendorRepository;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PurchaseController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private PurchaseRepository $purchaseRepo,
        private VendorRepository $vendorRepo,
    ) {}

    #[Route('/purchases', name: 'purchase_index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $purchases = $status === 'all'
            ? $this->purchaseRepo->findBy([], ['purchase_date' => 'DESC'])
            : $this->purchaseRepo->findBy(['status' => $status], ['purchase_date' => 'DESC']);

        $rows = [];
        foreach ($purchases as $purchase) {
            $rows[] = TableRow::create([
                'id' => $purchase->getId(),
                'purchase_number' => $purchase->getPurchaseNumber(),
                'vendor' => $purchase->getVendor()?->getName() ?? '—',
                'purchase_date' => $purchase->getPurchaseDate(),
                'expected_delivery' => $purchase->getExpectedDeliveryDate() ?? '—',
                'total' => '$' . number_format((float)$purchase->getTotalAmount(), 2),
                'status' => $purchase->getStatus(),
            ]);
        }

        $table = TableView::create('Purchase Orders')
            ->setFields([
                TableField::create('purchase_number', 'PO Number')->setSortable(true),
                TableField::create('vendor', 'Vendor')->setSortable(true),
                TableField::create('purchase_date', 'Purchase Date')->setSortable(true)->setType('date'),
                TableField::create('expected_delivery', 'Expected Delivery')->setType('date'),
                TableField::create('total', 'Total')->setSortable(true),
                TableField::create('status', 'Status')->setSortable(true),
            ])
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(
                TableAction::create('view', 'View')
                    ->setRoute('purchase_show')
                    ->setIcon('bi bi-eye')
                    ->setVariant('outline-primary')
            )
            ->addQuickAction(
                TableAction::create('edit', 'Edit')
                    ->setRoute('purchase_edit')
                    ->setIcon('bi bi-pencil')
                    ->setVariant('outline-secondary')
            )
            ->setSearchPlaceholder('Search purchase orders by PO number or vendor...')
            ->setEmptyState('No purchase orders found.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'purchase-list',
            'activeMenu' => 'purchase',
            'table' => $table,
            'bulkRoute' => 'purchase_bulk',
            'csrfSlug' => 'purchase_bulk',
        ]));
    }

    #[Route('/purchase/create', name: 'purchase_create')]
    public function create(Request $request): Response
    {
        $purchase = new Purchase();
        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->purchaseRepo->save($purchase);
            $this->addFlash('success', 'Purchase order created successfully.');
            return $this->redirectToRoute('purchase_index');
        }

        return $this->render('katzen/purchase/create_purchase.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'purchase-create',
            'activeMenu' => 'purchase',
            'form' => $form->createView(),
            'purchase' => null,
        ]));
    }

    #[Route('/purchase/{id}', name: 'purchase_show', requirements: ['id' => '\d+'])]
    public function show(Purchase $purchase): Response
    {
        return $this->render('katzen/purchase/show.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'purchase-view',
            'activeMenu' => 'purchase',
            'purchase' => $purchase,
        ]));
    }

    #[Route('/purchase/edit/{id}', name: 'purchase_edit')]
    public function edit(Request $request, Purchase $purchase): Response
    {
        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->purchaseRepo->save($purchase);
            $this->addFlash('success', 'Purchase order updated successfully.');
            return $this->redirectToRoute('purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('katzen/purchase/form.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'purchase-edit',
            'activeMenu' => 'purchase',
            'form' => $form->createView(),
            'purchase' => $purchase,
        ]));
    }

    #[Route('/purchases/bulk', name: 'purchase_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('purchase_bulk', $payload['_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
        }

        $action = $payload['action'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? []);

        if (empty($ids)) {
            return $this->json(['ok' => false, 'error' => 'No purchase orders selected'], 400);
        }

        $purchases = $this->purchaseRepo->findBy(['id' => $ids]);
        $count = count($purchases);

        switch ($action) {
            case 'approve':
                foreach ($purchases as $purchase) {
                    if ($purchase->getStatus() === 'draft') {
                        $purchase->setStatus('approved');
                    }
                }
                $this->purchaseRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count purchase order(s) approved"]);

            case 'cancel':
                foreach ($purchases as $purchase) {
                    $purchase->setStatus('cancelled');
                }
                $this->purchaseRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count purchase order(s) cancelled"]);

            case 'delete':
                foreach ($purchases as $purchase) {
                    $this->purchaseRepo->remove($purchase);
                }
                $this->purchaseRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count purchase order(s) deleted"]);

            default:
                return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
        }
    }
}