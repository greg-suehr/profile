<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\VendorInvoice;
use App\Katzen\Entity\VendorInvoiceItem;
use App\Katzen\Entity\Vendor;
use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\Account;
use App\Katzen\Repository\VendorInvoiceRepository;
use App\Katzen\Repository\VendorInvoiceItemRepository;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class VendorInvoiceService
{
  public function __construct(
    private EntityManagerInterface $em,
    private VendorInvoiceRepository $invoiceRepo,
    private VendorInvoiceItemRepository $itemRepo,
    private PurchaseRepository $purchaseRepo,
    private CostingService $costing,
  ) {}
  
  /**
   * Create a new vendor invoice
   */
  public function createInvoice(
    Vendor $vendor,
    string $invoiceNumber,
    \DateTimeInterface $invoiceDate,
    int $createdBy,
    ?\DateTimeInterface $dueDate = null,
    ?Purchase $purchase = null
  ): ServiceResponse
  {
    try {
      $existing = $this->invoiceRepo->findOneBy([
        'vendor' => $vendor,
        'invoice_number' => $invoiceNumber,
      ]);
      
      if ($existing) {
        return ServiceResponse::failure(
          errors: ['Invoice number already exists for this vendor'],
          message: 'Duplicate invoice number'
        );
      }
      
      $invoice = new VendorInvoice();
      $invoice->setVendor($vendor);
      $invoice->setInvoiceNumber($invoiceNumber);
      $invoice->setInvoiceDate($invoiceDate);
      $invoice->setDueDate($dueDate);
      $invoice->setCreatedBy($createdBy);
      $invoice->setPurchase($purchase);
      
      $this->em->persist($invoice);
      $this->em->flush();
      
      return ServiceResponse::success(
        data: ['invoice_id' => $invoice->getId()],
        message: 'Invoice created successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to create invoice'
      );
    }
  }

  /**
   * Add a line item to an invoice and optionally record price history
   *
   * @param itemData[]
   *  'description'
   *  'quantity'
   *  'unit_price'
   *  'expense_account'
   *  'stock_target'
   *  'unit_of_measure'
   *  'cost_center'
   *  'department'
   */
  public function addLineItem(
    VendorInvoice $invoice,
    array $itemData,
    bool $recordPriceHistory = true
  ): ServiceResponse
  {
    try {
      $item = new VendorInvoiceItem();
      $item->setVendorInvoice($invoice);
      $item->setDescription($itemData['description']);
      $item->setQuantity((string)$itemData['quantity']);
      $item->setUnitPrice((string)$itemData['unit_price']);
      $item->setExpenseAccount($itemData['expense_account']);
      
      if (isset($itemData['stock_target'])) {
        $item->setStockTarget($itemData['stock_target']);
      }
      
      if (isset($itemData['unit_of_measure'])) {
        $item->setUnitOfMeasure($itemData['unit_of_measure']);
      }
      
      if (isset($itemData['cost_center'])) {
        $item->setCostCenter($itemData['cost_center']);
      }
      
      if (isset($itemData['department'])) {
        $item->setDepartment($itemData['department']);
      }
      
      // Calculate line total
      $item->recalculateLineTotal();
      
      // Check for price variance if we have a stock target
      if ($item->getStockTarget()) {
        $this->checkPriceVariance($item);
      }
      
      $this->em->persist($item);
      $invoice->addItem($item);
      
      // Recalculate invoice totals
      $invoice->recalculateTotals();
      
      $this->em->flush();
      
      // Record price history if enabled and we have a stock target
      if ($recordPriceHistory && $item->getStockTarget()) {
        $this->costing->recordPricePoint(
          $invoice->getVendor(),
          $item->getStockTarget(),
          (float)$item->getUnitPrice(),
          $invoice->getInvoiceDate(),
          'invoice',
          $invoice->getId(),
          (float)$item->getQuantity()
                );
      }
      
      return ServiceResponse::success(
        data: ['item_id' => $item->getId()],
        message: 'Line item added successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to add line item'
      );
    }
  }

  /**
   * Approve an invoice
   */
  public function approveInvoice(VendorInvoice $invoice, int $userId): ServiceResponse
  {
    try {
      if ($invoice->getStatus() === 'void') {
        return ServiceResponse::failure(
          errors: ['Cannot approve a voided invoice'],
          message: 'Invalid invoice status'
        );
      }

      $invoice->approve($userId);
      $this->em->flush();
      
      return ServiceResponse::success(
        message: 'Invoice approved successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to approve invoice'
      );
    }
  }

  /**
   * Post an invoice (make it official and ready for payment)
   */
  public function postInvoice(VendorInvoice $invoice): ServiceResponse
  {
    try {
      if ($invoice->getStatus() === 'void') {
        return ServiceResponse::failure(
          errors: ['Cannot post a voided invoice'],
          message: 'Invalid invoice status'
        );
      }
      
      $invoice->post();
      $this->em->flush();
      
      return ServiceResponse::success(
        message: 'Invoice posted successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to post invoice'
      );
    }
  }
  
  /**
   * Record a payment against an invoice
   */
  public function recordPayment(
    VendorInvoice $invoice,
    float $amount,
    \DateTimeInterface $paymentDate
  ): ServiceResponse
  {
    try {
      if ($amount <= 0) {
        return ServiceResponse::failure(
          errors: ['Payment amount must be positive'],
          message: 'Invalid payment amount'
        );
      }
      
      if ($amount > $invoice->getAmountDue()) {
        return ServiceResponse::failure(
          errors: ['Payment amount exceeds invoice balance'],
          message: 'Payment amount too large'
        );
      }
      
      $invoice->markPaid($amount);
      $this->em->flush();
      
      return ServiceResponse::success(
        data: [
          'amount_paid' => $amount,
          'remaining_balance' => $invoice->getAmountDue(),
        ],
        message: 'Payment recorded successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to record payment'
      );
    }
  }

  /**
   * Reconcile an invoice with a purchase order
   */
  public function reconcileWithPurchase(
    VendorInvoice $invoice,
    Purchase $purchase
  ): ServiceResponse
  {
    try {
      // Match invoice items to purchase items
      $variances = [];
      $totalVariance = 0.0;
      
      foreach ($invoice->getItems() as $invoiceItem) {
        if (!$invoiceItem->getStockTarget()) {
          continue;
        }
        
        // Find matching PO item
        $poItem = null;
        foreach ($purchase->getItems() as $purchaseItem) {
          if ($purchaseItem->getStockTarget()->getId() === $invoiceItem->getStockTarget()->getId()) {
            $poItem = $purchaseItem;
            break;
          }
        }
        
        if ($poItem) {
          $invoiceItem->setPurchaseItem($poItem);
          $invoiceItem->setExpectedUnitPrice($poItem->getUnitPrice());
          $invoiceItem->checkPriceVariance();
          
          $lineVariance = $invoiceItem->getLineTotalVariance();
          if (abs($lineVariance) > 0.01) {
            $variances[] = [
              'item' => $invoiceItem->getDescription(),
              'variance' => $lineVariance,
              'variance_pct' => $invoiceItem->getPriceVariancePct(),
            ];
            $totalVariance += $lineVariance;
          }
        }
      }
      
      $invoice->setReconciled(true);
      $invoice->setVarianceTotal((string)$totalVariance);
      
      if (!empty($variances)) {
        $invoice->setVarianceNotes(json_encode($variances));
      }
      
      $this->em->flush();
      
      return ServiceResponse::success(
        data: [
          'total_variance' => $totalVariance,
          'line_variances' => $variances,
        ],
        message: 'Invoice reconciled with purchase order'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to reconcile invoice'
      );
    }
  }

  /**
   * Get overdue invoices
   * 
   * @return VendorInvoice[]
   */
  public function getOverdueInvoices(): array
  {
    return $this->invoiceRepo->findOverdue();
  }

  /**
   * Get invoices requiring approval
   * 
   * @return VendorInvoice[]
   */
  public function getInvoicesPendingApproval(): array
  {
    return $this->invoiceRepo->findBy([
      'approval_status' => 'pending',
      'status' => 'pending',
    ]);
  }

  /**
   * Void an invoice
   */
  public function voidInvoice(VendorInvoice $invoice, string $reason): ServiceResponse
  {
    try {
      if ($invoice->getAmountPaid() > 0) {
        return ServiceResponse::failure(
          errors: ['Cannot void an invoice that has been paid'],
          message: 'Invoice has payments'
        );
      }
      
      $invoice->void();
      $invoice->setVarianceNotes($reason);
      $this->em->flush();

      # TODO: canceled AP accounting
      
      return ServiceResponse::success(
        message: 'Invoice voided successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to void invoice'
      );
    }
  }
  
  /**
   * Check for price variance on an invoice item
   */
  private function checkPriceVariance(VendorInvoiceItem $item): void
  {
    if (!$item->getStockTarget() || !$item->getVendorInvoice()) {
      return;
    }
    
    // Get recent average price
    $avgPrice = $this->costing->getAveragePrice(
      $item->getStockTarget(),
      new \DateTime()->modify('-30 days'),
      new \DateTime(),
      $item->getVendorInvoice()->getVendor(),      
    );
    
    if ($avgPrice > 0) {
      $item->setExpectedUnitPrice((string)$avgPrice);
      $item->checkPriceVariance();
      
      // Flag if variance exceeds 10%
      if (abs((float)$item->getPriceVariancePct()) > 10) {
        $item->setVarianceFlagged(true);
      }
    }
  }

  /**
   * Import invoice from OCR/email data
   */
  public function importFromOCR(
    array $ocrData,
    int $createdBy
  ): ServiceResponse
  {
    try {
      // Extract vendor from OCR data
      $vendor = $this->findOrCreateVendor($ocrData['vendor_name']);
      
      $invoice = new VendorInvoice();
      $invoice->setVendor($vendor);
      $invoice->setInvoiceNumber($ocrData['invoice_number']);
      $invoice->setInvoiceDate(new \DateTime($ocrData['invoice_date']));
      $invoice->setCreatedBy($createdBy);
      $invoice->setSourceType('ocr_scan');
      $invoice->setOcrConfidence((string)($ocrData['confidence'] ?? 0.0));
      
      if (isset($ocrData['due_date'])) {
        $invoice->setDueDate(new \DateTime($ocrData['due_date']));
      }
      
      if (isset($ocrData['file_path'])) {
        $invoice->setOriginalFilePath($ocrData['file_path']);
      }
      
      $this->em->persist($invoice);
      
      // Add line items from OCR
      foreach ($ocrData['items'] as $itemData) {
        $this->addLineItem($invoice, $itemData, true);
      }
      
      $invoice->recalculateTotals();
      $this->em->flush();
      
      return ServiceResponse::success(
        data: ['invoice_id' => $invoice->getId()],
        message: 'Invoice imported from OCR successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to import invoice from OCR'
      );
    }
  }

  /**
   * Helper to find or create vendor
   */
  private function findOrCreateVendor(string $vendorName): Vendor
  {
    # TODO: implement findOrCreateVendor logic with approval workflow

    throw new \RuntimeException('Vendor lookup from OCR data not implemented');
  }
}
