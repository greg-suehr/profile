<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\DashboardContextService;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Form\ImportVendorInvoiceType;
use App\Katzen\Form\VendorInvoiceType;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\StockReceipt;
use App\Katzen\Entity\VendorInvoice;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\VendorInvoiceRepository;

use App\Katzen\Service\Accounting\VendorInvoiceService;
use App\Katzen\Service\Accounting\ChartOfAccountsService;
use App\Katzen\Service\Import\ReceiptImportService;

use App\Katzen\Adapter\OCRAdapter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/bill', name: 'vendor_invoice_', host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
final class VendorInvoiceController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private PurchaseRepository $purchaseRepo,
    private VendorInvoiceRepository $invoiceRepo,
    private ReceiptImportService $import,
    private VendorInvoiceService $invoicing,
    private ChartOfAccountsService $coa,
  )
  {}
  
  #[Route('/', name: 'index')]
  #[DashboardLayout('finance', 'vendor-invoice', 'vendor-invoice-table')]
  public function index(Request $request): Response
  {
    $status = $request->query->get('status', 'all');
    $invoices = $status === 'all'
      ? $this->invoiceRepo->findBy([], ['invoice_number' => 'ASC'])
      : $this->invoiceRepo->findBy(['status' => $status], ['invoice_number' => 'ASC']);

    $rows = [];
    foreach ($invoices as $invoice) {
      $rows[] = TableRow::create([
        'id' => $invoice->getId(),
        'invoice_number' => $invoice->getInvoiceNumber(),
        'invoice_date' => $invoice->getInvoiceDate(),
        'due_date' => $invoice->getDueDate(),
        'total_amount' => $invoice->getTotalAmount(),
        'variance_total' => $invoice->getVarianceTotal(),
        'status' => $invoice->getStatus(),
        'approval_status' => $invoice->getApprovalStatus(),
      ])->setId($invoice->getId());
    }

    $table = TableView::create('Vendor Invoices')
      ->addField(
        TableField::link('invoice_number', 'Invoice #', 'vendor_invoice_show')->sortable(),
      )
      ->addField(
        TableField::date('invoice_date', 'Invoice Date')->sortable(),
      )
      ->addField(
        TableField::date('due_date', 'Due Date')->sortable(),
      )      
      ->addField(
        TableField::currency('total_amount', 'Total')->sortable(),
      )
      ->addField(
        TableField::currency('variance_amount', 'Variance')->sortable(),
      )
      ->addField(
        TableField::badge('status', 'Status')
      )
      ->addField(
        TableField::badge('approval_status', 'Approval')
      )
      ->setRows($rows)
      ->setSelectable(true)
      ->addQuickAction(
        TableAction::custom('approve', 'Approve')
          ->setRoute('vendor_invoice_approve')
          ->setIcon('bi bi-check')
          ->setVariant('outline-primary')
      )
      ->addQuickAction(
        TableAction::custom('pay', 'Pay')
          ->setRoute('vendor_invoice_pay')
          ->setIcon('bi bi-cash')
          ->setVariant('outline-primary')
          )
      ->setSearchPlaceholder('Search by invoice number, vendor name, ...')
      ->setEmptyState('No vendor invoices found.')
      ->build();

    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'bulkRoute' => 'vendor_invoice_bulk',
      'csrfSlug' => 'vendor_invoice_bulk',
    ]));
  }

  #[Route('/bulk', name: 'bulk', methods: ['POST'])]
  public function bulk(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('vendor_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids = array_map('intval', $payload['ids'] ?? []);
    
    if (empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'No vendor invoices selected'], 400);
    }

    $invoices = $this->invoiceRepo->findBy(['id' => $ids]);
    $count = count($vendors);

    switch ($action) {
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
  } 
    
  #[Route('/create', name: 'create')]
  #[DashboardLayout('finance', 'vendor-invoice', 'vendor-invoice-create')]
  public function create(Request $request): Response
  {
    $invoice = new VendorInvoice();
    $form = $this->createForm(VendorInvoiceType::class, $invoice);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $this->invoiceRepo->save($invoice);
      $this->addFlash('success', 'Vendor invoice created successfully.');
      return $this->redirectToRoute('vendor_invoice_index');
    }
    
    return $this->render('katzen/vendor_invoice/create_vendor_invoice.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
      'invoice' => null,
    ]));
  }

  #[Route('/items/{id}', name: 'items')]
  #[DashboardLayout('finance', 'vendor-invoice', 'vendor-invoice-create')]
  public function invoiceItems(
    int $id,
    Request $request,
    SessionInterface $session
  ): Response {
    $wizardData = $session->get('wizard_data_vendor_invoice_create');
    if ($wizardData && $wizardData['vendor_invoice_id'] !== $id) {
      $this->addFlash('danger', 'Invalid vendor invoice ID. Please start the invoicing process again.');
      return $this->redirectToRoute('vendor_invoice_create');
    }

    $invoice = $this->invoiceRepo->find($id);
    if (!$invoice) {
      throw $this->createNotFoundException('Vendor invoice not found.');
    }

    $anyError = false;
    if ($request->isMethod('POST')) {
      
      $items = $request->request->all('items');
      
      if (empty($items)) {
        $this->addFlash('warning', 'Please enter quantities for at least one item.');
        return $this->redirectToRoute('vendor_invoice_items', ['id' => $id]);
      }

      $itemsData = [];
      foreach ($items as $itemId => $itemData) {
        if( is_null( $itemData['unit_price'] ) ) {
          $this->addFlash('warning', 'Please enter prices for all items.');
          return $this->redirectToRoute('vendor_invoice_items', ['id' => $id]);            
        }
        
        $unitPrice = $itemData['unit_price'] ?? null;

        $purchaseItem = null;
        foreach ($invoice->getPurchase()->getPurchaseItems() as $pi) {
          if ($pi->getId() === (int)$itemId) {
            $purchaseItem = $pi;
            break;
          }
        }

        $stockReceiptItem = null;
        foreach ($invoice->getStockReceipts() as $receipt) {
          foreach ($receipt->getStockReceiptItems() as $ri) {
            if ($ri->getId() === (int)$itemId) {
              $stockReceiptItem = $ri;
              break;
            }
          }
        }

        # TODO: straighten out param passing, object params
        # in Twig > Controller > Service data flows
        $lineData = [
          'stock_target' => $purchaseItem->getStockTarget(),
          'purchase_item' => $purchaseItem,
          'stock_receipt_item' => $stockReceiptItem,
          'description' => $itemData['description'],
          'quantity' => $itemData['quantity'],          
          'unit_of_measure' => $itemData['unit_of_measure_id'],
          'unit_price' => $itemData['unit_price'],          
          'cost_center' => null,
          'department' => null,
          'expense_account' => $this->coa->resolve('Goods Received Not Invoiced'), # TODO: not this
        ];
        
        $itemsData[] = $lineData;

        $result = $this->invoicing->addLineItem($invoice, $lineData);
        if ($result->isFailure()) {
          # TODO: much better handling and reporting of per-line errors
          $anyError = true;
        }
      }

      if (empty($itemsData)) {
        $this->addFlash('warning', 'Please enter quantities for at least one item.');
        # TODO: fix this bad redirect
        return $this->redirectToRoute('vendor_invoice_items', ['id' => $id]);
      }
    
      if ($anyError) {
        $this->addFlash('danger', $result->getMessage());
        foreach ($result->getErrors() as $error) {
          $this->addFlash('warning', $error);
        }      
      } else {
        $session->remove('wizard_data_vendor_invoice_create');
        $this->addFlash('success', $result->getMessage());
        return $this->redirectToRoute('vendor_invoice_show', [
          'id' => $result->getData()[$invoice->getId()],
        ]);
      }
    }

    return $this->render('katzen/vendor_invoice/vendor_invoice_items.html.twig', $this->dashboardContext->with([
      'invoice' => $invoice,
    ]));
  }

  #[Route('/create/from_receipt/{id}', name: 'create_from_receipt')]
  public function createFromReceipt(StockReceipt $receipt): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }    

  #[Route('/create/from_po/{po_id}', name: 'create_from_po')]
  public function createFromPurchase(
    int $po_id,
    SessionInterface $session
  ): Response
  {
     $purchase = $this->purchaseRepo->find($po_id);
     
     if (!$purchase) {
       throw $this->createNotFoundException('Purchase order not found.');
     }

     # TODO: design status lifecycle and pass non-blocking warnings based on status
     if (in_array($purchase->getStatus(), ['billed'])) {
       $this->addFlash('warning', 'This purchase order is already billed.');
       return $this->redirectToRoute('purchase_index');
     }

     $result = $this->invoicing->createInvoice(
         $purchase->getVendor(),
         $purchase->getPoNumber(),
         new \DateTime(),
         $this->getUser()->getId(),
         null,
         $purchase,
     );

     if ($result->isSuccess()) {
       $invoice_id = $result->getData()['invoice_id'];
       # No flash
       return $this->redirectToRoute('vendor_invoice_items', ['id' => $invoice_id]);
     }

     $this->addFlash('danger', $result->getMessage() ?: 'Unable to invoice purhcase order.');
     $this->addFlash('warning', implode('; ', (array)$result->getErrors()));
     
     return $this->redirectToRoute('vendor_invoice_index');
  }    

  #[Route('/view/{id}', name: 'show')]
  #[DashboardLayout('finance', 'vendor-invoice', 'invoice-detail')]
  public function show(VendorInvoice $invoice): Response
  {
     return $this->render('katzen/vendor_invoice/show_vendor_invoice.html.twig', $this->dashboardContext->with([
       'invoice' => $invoice,
     ]));
  }    

  #[Route('/approve/{id}', name: 'approve', methods: ['POST'])]
  public function approve(VendorInvoice $invoice): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }

  #[Route('/pay/{id}', name: 'pay', methods: ['POST'])]
  public function pay(VendorInvoice $invoice): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }

  #[Route('/void/{id}', name: 'void', methods: ['POST'])]
  public function void(VendorInvoice $invoice): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }    
    
  #[Route('/reconcile/{id}', name: 'reconcile')]
  public function reconcile(VendorInvoice $invoice): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }    
    
  #[Route('/match-receipts/{id}', name: 'match_receipts', methods: ['POST'])]
  public function matchReceipts(VendorInvoice $invoice, Request $request): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }    

  #[Route('/variance/{id}', name: 'variance')]
  public function viewVariances(VendorInvoice $invoice): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }    

  #[Route('/variance/approve/{id}', name: 'approve_variance', methods: ['POST'])]
  public function approveVariance(VendorInvoice $invoice, Request $request): Response
  {
    return $this->redirectToRoute('vendor_invoice_index');
  }
  
  /**
   * Import invoice via OCR scan
   * Add this route to your VendorInvoiceController class
   */
  #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
  #[DashboardLayout('finance', 'vendor-invoice', 'vendor-invoice-import')]
  public function importOCR(
    Request $request,
    OCRAdapter $ocrAdapter,
  ): Response
  {
    $form = $this->createForm(ImportVendorInvoiceType::class);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      /** @var UploadedFile $file */
      $file = $form->get('file')->getData();

      try {
        $ocrData = $ocrAdapter->process($file);

        $result = $this->import->processOCRResult(
          $ocrData,
          $this->getUser()->getId()
        );
            
        if ($result->isSuccess()) {
          $invoiceId = $result->getData()['invoice_id'];
                
          $this->addFlash('success', 'Invoice imported successfully!');
          return $this->redirectToRoute('vendor_invoice_show', ['id' => $invoiceId]);
        } else {
          throw new \RuntimeException($result->getMessage());
        }
        
      } catch (\Exception $e) {
        $this->addFlash('error', 'Error importing invoice: ' . $e->getMessage());
        // Log the error for debugging
        // $this->logger->error('OCR import failed', ['exception' => $e, 'file' => $file->getClientOriginalName()]);
      }
    }
    
    return $this->render('katzen/vendor_invoice/import.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
    ]));
  }
}
