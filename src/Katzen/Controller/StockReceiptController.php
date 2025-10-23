<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\StockReceipt;
use App\Katzen\Form\StockReceiptType;
use App\Katzen\Repository\StockReceiptRepository;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StockReceiptController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private StockReceiptRepository $receiptRepo,
        private PurchaseRepository $purchaseRepo,
    ) {}

    #[Route('/receipts', name: 'receipt_index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $receipts = $status === 'all'
            ? $this->receiptRepo->findBy([], ['received_date' => 'DESC'])
            : $this->receiptRepo->findBy(['status' => $status], ['received_date' => 'DESC']);

        $rows = [];
        foreach ($receipts as $receipt) {
            $rows[] = TableRow::create([
                'id' => $receipt->getId(),
                'receipt_number' => $receipt->getReceiptNumber(),
                'purchase' => $receipt->getPurchase()?->getPurchaseNumber() ?? '—',
                'vendor' => $receipt->getPurchase()?->getVendor()?->getName() ?? '—',
                'received_date' => $receipt->getReceivedDate(),
                'received_by' => $receipt->getReceivedBy() ?? '—',
                'status' => $receipt->getStatus(),
            ]);
        }

        $table = TableView::create('Receipts')
            ->setFields([
                TableField::create('receipt_number', 'Receipt #')->setSortable(true),
                TableField::create('purchase', 'PO Number')->setSortable(true),
                TableField::create('vendor', 'Vendor')->setSortable(true),
                TableField::create('received_date', 'Received Date')->setSortable(true)->setType('date'),
                TableField::create('received_by', 'Received By'),
                TableField::create('status', 'Status')->setSortable(true),
            ])
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(
                TableAction::create('view', 'View')
                    ->setRoute('receipt_show')
                    ->setIcon('bi bi-eye')
                    ->setVariant('outline-primary')
            )
            ->addQuickAction(
                TableAction::create('edit', 'Edit')
                    ->setRoute('receipt_edit')
                    ->setIcon('bi bi-pencil')
                    ->setVariant('outline-secondary')
            )
            ->setSearchPlaceholder('Search receipts by number, PO, or vendor...')
            ->setEmptyState('No receipts found.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'receipt-list',
            'activeMenu' => 'receipt',
            'table' => $table,
            'bulkRoute' => 'receipt_bulk',
            'csrfSlug' => 'receipt_bulk',
        ]));
    }

    #[Route('/receipt/create', name: 'receipt_create')]
    public function create(Request $request): Response
    {
        $receipt = new StockReceipt();
        
        // If a purchase ID is provided, pre-populate the receipt
        $purchaseId = $request->query->get('purchase');
        if ($purchaseId) {
            $purchase = $this->purchaseRepo->find($purchaseId);
            if ($purchase) {
                $receipt->setPurchase($purchase);
            }
        }

        $form = $this->createForm(StockReceiptType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->receiptRepo->save($receipt);
            $this->addFlash('success', 'Receipt created successfully.');
            return $this->redirectToRoute('receipt_index');
        }

        return $this->render('katzen/receipt/create_receipt.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'receipt-create',
            'activeMenu' => 'receipt',
            'form' => $form->createView(),
            'receipt' => null,
        ]));
    }

    #[Route('/receipt/{id}', name: 'receipt_show', requirements: ['id' => '\d+'])]
    public function show(StockReceipt $receipt): Response
    {
        return $this->render('katzen/receipt/show.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'receipt-view',
            'activeMenu' => 'receipt',
            'receipt' => $receipt,
        ]));
    }

    #[Route('/receipt/edit/{id}', name: 'receipt_edit')]
    public function edit(Request $request, StockReceipt $receipt): Response
    {
        $form = $this->createForm(StockReceiptType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->receiptRepo->save($receipt);
            $this->addFlash('success', 'Receipt updated successfully.');
            return $this->redirectToRoute('receipt_show', ['id' => $receipt->getId()]);
        }

        return $this->render('katzen/receipt/form.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'receipt-edit',
            'activeMenu' => 'receipt',
            'form' => $form->createView(),
            'receipt' => $receipt,
        ]));
    }

    #[Route('/receipts/bulk', name: 'receipt_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('receipt_bulk', $payload['_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
        }

        $action = $payload['action'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? []);

        if (empty($ids)) {
            return $this->json(['ok' => false, 'error' => 'No receipts selected'], 400);
        }

        $receipts = $this->receiptRepo->findBy(['id' => $ids]);
        $count = count($receipts);

        switch ($action) {
            case 'verify':
                foreach ($receipts as $receipt) {
                    if ($receipt->getStatus() === 'pending') {
                        $receipt->setStatus('verified');
                    }
                }
                $this->receiptRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count receipt(s) verified"]);

            case 'complete':
                foreach ($receipts as $receipt) {
                    $receipt->setStatus('complete');
                }
                $this->receiptRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count receipt(s) completed"]);

            case 'delete':
                foreach ($receipts as $receipt) {
                    $this->receiptRepo->remove($receipt);
                }
                $this->receiptRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count receipt(s) deleted"]);

            default:
                return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
        }
    }
}
