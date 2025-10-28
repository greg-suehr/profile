<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\StockReceipt;
use App\Katzen\Form\StockReceiptType;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\StockReceiptRepository;
use App\Katzen\Repository\StockLocationRepository;
use App\Katzen\Service\Inventory\StockReceiptService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class StockReceiptController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private StockReceiptService $receiptService,
        private PurchaseRepository $purchaseRepo,
        private StockReceiptRepository $receiptRepo,
        private StockLocationRepository $locationRepo,
    ) {}

  #[Route('/receipts', name: 'receipt_index')]
  #[DashboardLayout('supply', 'receipt', 'receipt-table')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $receipts = $status === 'all'
            ? $this->receiptRepo->findBy([], ['received_at' => 'DESC'])
            : $this->receiptRepo->findBy(['status' => $status], ['received_at' => 'DESC']);

        $rows = [];
        foreach ($receipts as $receipt) {
            $rows[] = TableRow::create([
                'id' => $receipt->getId(),
                'receipt_number' => $receipt->getReceiptNumber(),
                'purchase' => $receipt->getPurchase()?->getPoNumber() ?? '—',
                'vendor' => $receipt->getPurchase()?->getVendor()?->getName() ?? '—',
                'received_at' => $receipt->getReceivedAt(),
                'received_by' => $receipt->getReceivedBy() ?? '—',
                'status' => $receipt->getStatus(),
            ])->setId($receipt->getId());             
        }

        $table = TableView::create('Receipts')
            ->addField(
              TableField::text('receipt_number', 'Receipt #')->sortable()
                )
            ->addField(
              TableField::text('purchase', 'PO Number')->sortable()
                )
            ->addField(
              TableField::text('vendor', 'Vendor')->sortable()
                )
            ->addField(
              TableField::date('received_at', 'Received Date')->sortable()
                )
            ->addField(
              TableField::text('received_by', 'Received By')
                )
            ->addField(
              TableField::badge('status', 'Status')->sortable()
                )
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
            'table' => $table,
            'bulkRoute' => 'receipt_bulk',
            'csrfSlug' => 'receipt_bulk',
        ]));
    }

  #[Route('/stock/receipt/create', name: 'receipt_create')]
  #[DashboardLayout('supply', 'receipt', 'receipt-create')]
  public function create(Request $request, SessionInterface $session): Response
  {
    $receipt = new StockReceipt();
    
    $defaultLocation = $this->receiptService->getDefaultLocation();
    if ($defaultLocation) {
      $receipt->setLocation($defaultLocation);
    }
    
    $form = $this->createForm(StockReceiptType::class, $receipt);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $session->set('receipt_data', [
        'purchase_id' => $receipt->getPurchase()->getId(),
        'location_id' => $receipt->getLocation()->getId(),
        'received_at' => $receipt->getReceivedAt()->format('Y-m-d H:i:s'),
        'notes' => $receipt->getNotes(),
      ]);
      
      return $this->redirectToRoute('receipt_items', [
        'id' => $receipt->getPurchase()->getId(),
      ]);
    }
    
    return $this->render('katzen/receipt/create.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
    ]));
  }

  #[Route('/stock/receipt/items/{id}', name: 'receipt_items')]
  #[DashboardLayout('supply', 'receipt', 'receipt-create')]
  public function receiveItems(
    int $id,
    Request $request,
    SessionInterface $session
  ): Response {
    $receiptData = $session->get('receipt_data');
    if (!$receiptData || $receiptData['purchase_id'] !== $id) {
      $this->addFlash('danger', 'Please start the receiving process again.');
      return $this->redirectToRoute('receipt_create');
    }
    
    $purchase = $this->purchaseRepo->find($id);
    if (!$purchase) {
      throw $this->createNotFoundException('Purchase order not found.');
    }
    
    if ($request->isMethod('POST')) {
      $items = $request->request->all('items');
      
      if (empty($items)) {
        $this->addFlash('warning', 'Please enter quantities for at least one item.');
        return $this->redirectToRoute('receipt_items', ['id' => $id]);
      }
      
      $itemsData = [];
      foreach ($items as $itemId => $itemData) {
        $qtyReceived = $itemData['qty_received'] ?? null;
        
        if ($qtyReceived && (float)$qtyReceived > 0) {
          $purchaseItem = null;
          foreach ($purchase->getPurchaseItems() as $pi) {
            if ($pi->getId() === (int)$itemId) {
              $purchaseItem = $pi;
              break;
            }
          }
          
          if ($purchaseItem) {
            $itemsData[] = [
              'purchase_item' => $purchaseItem,
              'qty_received' => $qtyReceived,
              'lot_number' => $itemData['lot_number'] ?? null,
              'expiration_date' => !empty($itemData['expiration_date']) 
                                ? new \DateTime($itemData['expiration_date']) 
                                : null,
              'production_date' => !empty($itemData['production_date']) 
                                ? new \DateTime($itemData['production_date']) 
                                : null,
              'notes' => $itemData['notes'] ?? null,
            ];
          }
        }
      }
      
      if (empty($itemsData)) {
        $this->addFlash('warning', 'Please enter valid quantities for at least one item.');
        return $this->redirectToRoute('receipt_items', ['id' => $id]);
      }
      
      $location = $receiptData['location_id']
        ? $this->locationRepo->find($receiptData['location_id'])
        : $this->receiptService->getDefaultLocation();

      $result = $this->receiptService->createReceipt($purchase, [
        'received_at' => new \DateTime($receiptData['received_at']),
        'notes' => $receiptData['notes'] ?? null,
        'location' => $location,
        'items' => $itemsData,
      ]);
      
      if ($result->isSuccess()) {
        $session->remove('receipt_data');
        $this->addFlash('success', $result->getMessage());
        return $this->redirectToRoute('receipt_show', [
          'id' => $result->getData()['receipt_id'],
        ]);
      } else {
        $this->addFlash('danger', $result->getMessage());
        foreach ($result->getErrors() as $error) {
          $this->addFlash('warning', $error);
        }
      }
    }
    
    return $this->render('katzen/receipt/receive_items.html.twig', $this->dashboardContext->with([
      'purchase' => $purchase,
      'receiptData' => $receiptData,
    ]));    
    }

    #[Route('/stock/receipt/{id}', name: 'receipt_show')] 
    #[DashboardLayout('supply', 'receipt', 'receipt-show')]   
    public function show(int $id): Response
    {
        $receipt = $this->receiptRepo->find($id);
        
        if (!$receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        return $this->render('katzen/receipt/show_receipt.html.twig',
            $this->dashboardContext->with([
                'receipt' => $receipt,
            ])
        );
    }

    #[Route('/receipt/from_po/{po_id}', name: 'receipt_from_po')]
    #[DashboardLayout('supply', 'receipt', 'receipt-create')]
    public function createFromPo(int $po_id, SessionInterface $session): Response
    {
      $purchase = $this->purchaseRepo->find($po_id);
        
      if (!$purchase) {
        throw $this->createNotFoundException('Purchase order not found.');
      }

      if (!in_array($purchase->getStatus(), ['pending', 'partial'])) {
        $this->addFlash('warning', 'This purchase order is not available for receiving.');
        return $this->redirectToRoute('receipt_index');
      }
      
      $defaultLocation = $this->receiptService->getDefaultLocation();
      
      $session->set('receipt_data', [
        'purchase_id' => $purchase->getId(),
        'location_id' => $defaultLocation?->getId(),
        'received_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        'notes' => null,
      ]);
      
      return $this->redirectToRoute('receipt_items', ['id' => $po_id]);
  }
  
    #[Route('/receipt/edit/{id}', name: 'receipt_edit')]
      #[DashboardLayout('supply', 'receipt', 'receipt-create')]      
    public function edit(Request $request, StockReceipt $receipt): Response
    {
        $form = $this->createForm(StockReceiptType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->receiptRepo->save($receipt);
            $this->addFlash('success', 'Receipt updated successfully.');
            return $this->redirectToRoute('receipt_show', ['id' => $receipt->getId()]);
        }

        return $this->render('katzen/receipt/create_receipt.html.twig', $this->dashboardContext->with([
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
