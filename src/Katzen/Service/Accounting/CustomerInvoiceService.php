<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Invoice;
use App\Katzen\Entity\InvoiceLineItem;
use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\Account;
use App\Katzen\Repository\InvoiceRepository;
use App\Katzen\Repository\InvoiceLineItemRepository;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerInvoiceService
{
  public function __construct(
    private EntityManagerInterface $em,
    private InvoiceRepository $invoiceRepo,
    private InvoiceLineItemRepository $itemRepo,
    private OrderRepository $orderRepo,
    private CostingService $costing,
  ) {}
  
  /**
   * Create a new customer invoice
   */
  public function createInvoice(
    Customer $customer,
    string $invoiceNumber,
    \DateTimeInterface $invoiceDate,
    int $createdBy,
    ?\DateTimeInterface $dueDate = null,
    ?Order $order = null
  ): ServiceResponse
  {
    try {
      $existing = $this->invoiceRepo->findOneBy([
        'customer' => $customer,
        'invoice_number' => $invoiceNumber,
      ]);
      
      if ($existing) {
        return ServiceResponse::failure(
          errors: ['Invoice number already exists for this customer'],
          message: 'Duplicate invoice number'
        );
      }
      
      $invoice = new Invoice();
      $invoice->setCustomer($customer);
      $invoice->setInvoiceNumber($invoiceNumber);
      $invoice->setInvoiceDate($invoiceDate);
      $invoice->setDueDate($dueDate);
      $invoice->addOrder($order);
      
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
    Invoice $invoice,
    array $itemData,
    bool $recordPriceHistory = true
  ): ServiceResponse
  {
    try {
      $item = new InvoiceLineItem();
      $item->setInvoice($invoice);
      $item->setDescription($itemData['description']);
      $item->setQuantity((string)$itemData['quantity']);
      $item->setUnitPrice((string)$itemData['unit_price']);
            
      // Calculate line total
      $item->calculateLineTotal();
            
      $this->em->persist($item);
      $invoice->addLineItem($item);
      
      // Recalculate invoice totals
      $invoice->calculateTotals();
      
      $this->em->flush();      
      
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
   * Post an invoice (make it official and ready for payment)
   */
  public function postInvoice(Invoice $invoice): ServiceResponse
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
    Invoice $invoice,
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
   * Get overdue invoices
   * 
   * @return Invoice[]
   */
  public function getOverdueInvoices(): array
  {
    return $this->invoiceRepo->findOverdue();
  }

  /**
   * Void an invoice
   */
  public function voidInvoice(Invoice $invoice, string $reason): ServiceResponse
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

      # TODO: canceled AR accounting
      
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
   * Helper to find or create customer
   */
  private function findOrCreateCustomer(array $customerInfo): Vendor
  {
    # TODO: implement findOrCreateCustomer logic

    throw new \RuntimeException('Customer lookup from order data not implemented');
  }
}
