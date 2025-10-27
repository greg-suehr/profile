<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Purchase;
use App\Katzen\Form\PurchaseType;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\VendorRepository;
use App\Katzen\Service\PurchaseService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PurchaseController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private PurchaseService $purchasing,
        private PurchaseRepository $purchaseRepo,
        private VendorRepository $vendorRepo,
    ) {}

  #[Route('/purchases', name: 'purchase_index')]
  #[DashboardLayout('supply', 'purchase', 'purchase-table')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $purchases = $status === 'all'
            ? $this->purchaseRepo->findBy([], ['order_date' => 'DESC'])
            : $this->purchaseRepo->findBy(['status' => $status], ['order_date' => 'DESC']);

        $rows = [];
        foreach ($purchases as $purchase) {
            $rows[] = TableRow::create([
                'id' => $purchase->getId(),
                'purchase_number' => $purchase->getPoNumber(),
                'vendor' => $purchase->getVendor()?->getName() ?? '—',
                'vendor_id' => $purchase->getVendor()?->getId() ?? '-',
                'order_date' => $purchase->getOrderDate(),
                'expected_delivery' => $purchase->getExpectedDelivery() ?? '—',
                'total' => '$' . number_format((float)$purchase->getTotalAmount(), 2),
                'status' => $purchase->getStatus(),
            ])
            ->setId($purchase->getId());
        }

        $table = TableView::create('Purchase Orders')
            ->addField(
              TableField::link('purchase_number', 'PO Number', 'purchase_show')->sortable()
                )
            ->addField(
              TableField::link('vendor', 'Vendor', 'vendor_show')->setAltId('vendor_id')->sortable()
                )
            ->addField(
              TableField::date('order_date', 'Purchase Date')->sortable()
                )
            ->addField(
              TableField::date('expected_delivery', 'Expected Delivery')->sortable()
                )
            ->addField(
              TableField::currency('total', 'Total')->sortable()
                )
            ->addField(
              TableField::status('status', 'Status')->sortable()
            )
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
            'table' => $table,
            'bulkRoute' => 'purchase_bulk',
            'csrfSlug' => 'purchase_bulk',
        ]));
    }

  #[Route('/purchase/create', name: 'purchase_create')]
  #[DashboardLayout('supply', 'purchase', 'purchase-create')]  
    public function create(Request $request): Response
    {
        $purchase = new Purchase();
        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->purchasing->createPurchaseOrder($purchase);

            if ($response->isSuccess()) {
              $this->addFlash('success', $response->getMessage());
              return $this->redirectToRoute('purchase_index');
            }
            
            foreach ($response->getErrors() as $error) {
              $this->addFlash('danger', $error);
            }
        }

        return $this->render('katzen/purchase/create_purchase.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'purchase' => null,
        ]));
    }

  #[Route('/purchase/{id}', name: 'purchase_show', requirements: ['id' => '\d+'])]
  #[DashboardLayout('supply', 'purchase', 'purchase-show')]
    public function show(Purchase $purchase): Response
    {
        return $this->render('katzen/purchase/show_purchase.html.twig', $this->dashboardContext->with([
            'purchase' => $purchase,
        ]));
    }

  #[Route('/purchase/edit/{id}', name: 'purchase_edit')]
  #[DashboardLayout('supply', 'purchase', 'purchase-create')]
    public function edit(Request $request, Purchase $purchase): Response
    {
        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->purchaseRepo->save($purchase);
            $this->addFlash('success', 'Purchase order updated successfully.');
            return $this->redirectToRoute('purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('katzen/purchase/create_purchase.html.twig', $this->dashboardContext->with([
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
