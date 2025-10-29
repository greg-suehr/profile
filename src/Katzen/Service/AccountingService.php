<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Account;
use App\Katzen\Entity\Customer;
use App\Katzen\Entity\LedgerEntry;
use App\Katzen\Entity\LedgerEntryLine;
use App\Katzen\Entity\Invoice;
use App\Katzen\Entity\InvoiceLineItem;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\Payment;
use App\Katzen\Repository\AccountRepository;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Repository\InvoiceRepository;
use App\Katzen\Repository\LedgerEntryRepository;
use App\Katzen\Repository\PaymentRepository;
use App\Katzen\Service\Accounting\ChartOfAccountsService;
use App\Katzen\Service\Accounting\CostingService;
use App\Katzen\Service\Accounting\JournalEventService;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class AccountingService
{
  public function __construct(
    private EntityManagerInterface $em,
    private CustomerRepository $customerRepo,
    private InvoiceRepository $invoiceRepo,
    private LedgerEntryRepository $entries,
    private PaymentRepository $paymentRepo,
    private ChartOfAccountsService $coa,    
    private CostingService $costing,
    private JournalEventService $journalEvents,
  ) {}

  /**
   * ! NOTE: use Accounting/recordEvent to map financially impactful actions to
   * ! the standard set of events defined in the JournalEventsService.
   * !
   * ! Review the documentation in the JournalEventsService and consider expanding
   * ! the set of known JournalEvents if needed to implement a new workflow.
   * 
   * Write a direct journal entry.
   *
   * * @param string $transactionType ('sale', 'purchase', 'cogs', 'adjustment')
   * * @param list<array{account:string, debit:?float, credit:?float}> $lines proto-LedgerEntryLines
   * * @param string $referenceType ('order', 'stock_transaction', 'invoice')
   * * @param string $referenceId Sorry for string cast but accomodates non-numeric object IDs
   * * @param list<array{metadataKey:string => metadataValue:string}>
   * * @return ServiceResponse
   */
  public function record(
    string $transactionType,
    array $lines,
    string $referenceType,
    int $referenceId,
    array $metadata = []
  ): ServiceResponse {
    $totalDebit = 0;
    $totalCredit = 0;
    
    foreach ($lines as $line) {
      $totalDebit += (float)($line['debit'] ?? 0);
      $totalCredit += (float)($line['credit'] ?? 0);
    }
        
    if (abs($totalDebit - $totalCredit) > 0.01) {
      return ServiceResponse::error(
        "Journal entry out of balance. Debits: {$totalDebit}, Credits: {$totalCredit}"
      );
    }
    
    $entry = new LedgerEntry();
    $entry->setTimestamp(new \DateTime());
    $entry->setTransactionType($transactionType);
    $entry->setReferenceType($referenceType);
    $entry->setReferenceId((string)$referenceId);
    $entry->setIsReconciled(false);
    
    foreach ($lines as $lineData) {
      $account = $this->coa->resolve($lineData['account']);
      
      $line = new LedgerEntryLine();
      $line->setAccount($account);
      $line->setDebit($lineData['debit'] ?? '0.00');
      $line->setCredit($lineData['credit'] ?? '0.00');
      $line->setMemo($lineData['memo'] ?? null);
      
      $entry->addLine($line);
    }
    
    $this->em->persist($entry);
    $this->em->flush();

    return ServiceResponse::success(
       data: [
          'ledger_entry_id' => $entry->getId(),
          'lines' => $lines,
          'total_debit' => $totalDebit,
          'total_credit' => $totalCredit,
        ],
        message: "Journal entry {$entry->getId()} recorded"
    );
  }

  /**
   * Applies a standard JournalEvent to write a set of LedgerEntries so that your
   * programming problems don't become accounting problems.
   *
   * Refer to the JournalEventsService for details on typees of JournalEvents.
   *
   * * @param string $templateName (refer to JournalEventService)
   * * @param list<array{expr_key:string, amount:?float}> $amounts (refer to JournalEventService)
   * * @param string $referenceType ('order', 'stock_transaction', 'invoice')
   * * @param string $referenceId Sorry for string cast but accomodates non-numeric object IDs
   * * @param list<array{metadataKey:string => metadataValue:string}> $metadata
   * * @return ServiceResponse
   */
  public function recordEvent(
        string $templateName,
        array $amounts,
        string $referenceType,
        string $referenceId,
        array $metadata = []
    ): ServiceResponse {
        $template = $this->journalEvents->get($templateName);        
        $lines = $template->buildLines($amounts, $metadata);
        
        return $this->record(
            transactionType: $template->getTransactionType(),
            lines: $lines,
            referenceType: $referenceType,
            referenceId: $referenceId,
            metadata: $metadata
        );
    }
  

  /**
   * Create an invoice from a given Order entity.
   *
   * Converts an Order and its OrderItems into a corresponding Invoice and
   * associated InvoiceLineItems.
   * 
   * Calling this method with NO `$options` will reference Customer and Order
   * attributes to default payment terms, taxes, discounts, and other options.
   *
   * * @param Order $order The source order from which to build the invoice
   * * @param array $options Override parameters (e.g. payment_terms_days, tax_rate, discount_amount, notes)
   * * @return ServiceResponse Returns success with invoice summary or failure with details
   */
  public function createInvoiceFromOrder(Order $order, array $options = []): ServiceResponse
  {
    try {
      if (!$order->getCustomerEntity()) {
        return ServiceResponse::failure(
          errors: ['Order must have an associated customer.'],
          message: 'Invoice creation failed.',
          data: ['order_id' => $order->getId()]
        );
      }

      $invoice = new Invoice();
      $invoice->setInvoiceNumber($this->generateInvoiceNumber());
      $invoice->setCustomer($order->getCustomerEntity());
      $invoice->setOrder($order);
      $invoice->setInvoiceDate(new \DateTime());
            
      $dueDate = new \DateTime();
      $dueDate->modify('+' . ($options['payment_terms_days'] ?? 30) . ' days');
      $invoice->setDueDate($dueDate);
            
      $invoice->setTaxRate($options['tax_rate'] ?? '0.00');
      $invoice->setDiscountAmount($options['discount_amount'] ?? '0.00');
      $invoice->setNotes($options['notes'] ?? null);
      $invoice->setStatus('draft');
      
      foreach ($order->getOrderItems() as $orderItem) {
        $recipe = $orderItem->getRecipeListRecipeId();
        if (!$recipe) continue;
        
        $lineItem = new InvoiceLineItem();
        $lineItem->setDescription($recipe->getTitle());
        $lineItem->setQuantity((string)($orderItem->getQuantity() ?? 1));
        $lineItem->setUnitPrice($orderItem->getUnitPrice() ?? '0.00');
        $lineItem->setRecipe($recipe);
        
        $invoice->addLineItem($lineItem);
      }
      
      $invoice->calculateTotals();
      
      $this->invoiceRepo->save($invoice);

      $invoiceTotal = (float)$invoice->getTotalAmount();
      $applyPrepayment = 0.0;

      # TODO: design prepayment data flows, e.g.
      if ($order->getCustomerEntity()) {
        $applyPrepayment = $options['apply_prepayment'] ?? 0.0;
      }

      $journalEvent = $this->recordEvent(
        templateName: 'invoice_reclass_unbilled_to_ar',
        amounts: [
          'invoice_total' => $invoiceTotal,
          'apply_prepayment' => $applyPrepayment,
        ],
        referenceType: 'invoice',
        referenceId: (string)$invoice->getId(),
        metadata: [
          'order_id' => $order->getId(),
          'customer_id' => $invoice->getCustomer()->getId(),
        ]
      );

      if ($journalEvent->isFailure()) {
        # TODO: message soft failure on accounting errors
        #       retry on transient / volume errors
        #       forward instructions on configuration errors
      }
      
      return ServiceResponse::success(
        data: [
          'invoice_id' => $invoice->getId(),
          'invoice_number' => $invoice->getInvoiceNumber(),
          'total_amount' => $invoice->getTotalAmount(),
          'due_date' => $invoice->getDueDate()->format('Y-m-d'),
        ],
        message: 'Invoice created successfully.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to create invoice: ' . $e->getMessage()],
        message: 'Invoice creation failed.',
        data: ['order_id' => $order->getId()],
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Record a customer payment against an invoice.
   *
   * Creates and persists a Payment entity, updates Customer balance,
   * and applies the payment against the associated Invoice if provided.
   * Automatically adjusts invoice status (partial, paid, etc.) as appropriate.
   *
   * * @param Customer $customer The customer making the payment
   * * @param float $amount Payment amount (must be positive)
   * * @param string $paymentMethod The method used (e.g. 'cash', 'card', 'check')
   * * @param ?Invoice $invoice Optional linked invoice to apply payment toward
   * * @param array $options Optional metadata (e.g. payment_date, transaction_reference, notes)
   * * @return ServiceResponse Returns success with updated balances or failure with details
   */
  public function recordPayment(
    Customer $customer,
    float $amount,
    string $paymentMethod,
    ?Invoice $invoice = null,
    array $options = []
  ): ServiceResponse {
    try {
      if ($amount <= 0) {
        return ServiceResponse::failure(
          errors: ['Payment amount must be positive.'],
          message: 'Payment recording failed.',
          data: [
            'customer_id' => $customer->getId(),
            'invoice_id' => $invoice ? $invoice->getId() : null,
          ]
        );
      }
          
      $payment = new Payment();
      $payment->setPaymentNumber($this->generatePaymentNumber());
      $payment->setCustomer($customer);
      $payment->setInvoice($invoice);
      $payment->setAmount((string)$amount);
      $payment->setPaymentMethod($paymentMethod);
      $payment->setPaymentDate($options['payment_date'] ?? new \DateTime());
      $payment->setTransactionReference($options['transaction_reference'] ?? null);
      $payment->setNotes($options['notes'] ?? null);
      $payment->setStatus('pending');
      
      $this->paymentRepo->add($payment);
      
      if ($invoice) {
        $currentPaid = (float)$invoice->getAmountPaid();
        $newPaid = $currentPaid + $amount;
        $invoice->setAmountPaid((string)$newPaid);
        
        $amountDue = (float)$invoice->getTotalAmount() - $newPaid;
        $invoice->setAmountDue((string)max(0, $amountDue));
        
        if ($amountDue <= 0.01) {
          $invoice->setStatus('paid');
          $invoice->setPaidDate(new \DateTime());
        } elseif ($currentPaid == 0 && $newPaid > 0) {
          $invoice->setStatus('partial');
        }
      }
      else {
        # TODO: apply headless payments to next payable invoice or as credits
        return ServiceResponse::failure(
          errors: ['No invoice selected for payment application.'],
          message: 'Please provide an invoice number to apply a payment.',
          data: [
            'customer_id' => $customer->getId(),
            'invoice_id' => $invoice ? $invoice->getId() : null,
          ],
          metadata: []
        );
      }

       $eventResult = $this->recordEvent(
        templateName: 'customer_payment',
        amounts: [
          'amount' => $amount,
        ],
        referenceType: 'payment',
        referenceId: (string)$payment->getId(),
        metadata: [
          'customer_id' => $customer->getId(),
          'invoice_id' => $invoice->getId(),
          'payment_method' => $paymentMethod,
        ]
      );
      
      if ($eventResult->isFailure()) {
        error_log("Failed to record payment journal entry: " . $eventResult->getFirstError());
      }
      
      $currentBalance = (float)$customer->getAccountBalance();
      $customer->setAccountBalance((string)($currentBalance - $amount));
      
      $payment->setStatus('completed');
      $this->paymentRepo->save($payment);
      
      return ServiceResponse::success(
        data: [
          'payment_id' => $payment->getId(),
          'payment_number' => $payment->getPaymentNumber(),
          'amount' => $amount,
          'new_balance' => $customer->getAccountBalance(),
          'invoice_status' => $invoice?->getStatus(),
        ],
        message: 'Payment recorded successfully.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to record payment: ' . $e->getMessage()],
        message: 'Payment recording failed.',
        data: [
          'customer_id' => $customer->getId(),
          'invoice_id' => $invoice ? $invoice->getId() : null,
        ],
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  

  /**
   * Retrieve a customer account statement for a given date range.
   *
   * Aggregates invoices and payments for a customer over the specified period,
   * computes totals and overdue balances, and returns a structured summary.
   *
   * * @param Customer $customer The customer to generate a statement for
   * * @param ?\DateTimeInterface $from Start date (defaults to 90 days prior)
   * * @param ?\DateTimeInterface $to End date (defaults to today)
   * * @return ServiceResponse Returns structured summary with invoices and payments
   */
  public function getCustomerStatement(
    Customer $customer,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null
  ): ServiceResponse
  {
    try {
      $from = $from ?? (new \DateTime())->modify('-90 days');
      $to = $to ?? new \DateTime();

      # TODO: port to utility methods for the Customer entity
      $invoices = $this->invoiceRepo->createQueryBuilder('i')
                ->where('i.customer = :customer')
                ->andWhere('i.invoice_date BETWEEN :from AND :to')
                ->setParameter('customer', $customer)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->orderBy('i.invoice_date', 'DESC')
                ->getQuery()
                ->getResult();

      $payments = $this->paymentRepo->createQueryBuilder('p')
                ->where('p.customer = :customer')
                ->andWhere('p.payment_date BETWEEN :from AND :to')
                ->setParameter('customer', $customer)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->orderBy('p.payment_date', 'DESC')
                ->getQuery()
                ->getResult();

      $totalInvoiced = 0.0;
      $totalPaid = 0.0;
      $overdueAmount = 0.0;
      $now = new \DateTime();
      
      foreach ($invoices as $inv) {
        $totalInvoiced += (float)$inv->getTotalAmount();
        if ($inv->getStatus() !== 'paid' && $inv->getDueDate() < $now) {
          $overdueAmount += (float)$inv->getAmountDue();
        }
      }
      
      foreach ($payments as $pay) {
        if ($pay->getStatus() === 'completed') {
          $totalPaid += (float)$pay->getAmount();
        }
      }
      
      return ServiceResponse::success(
        data: [
          'customer' => [
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'account_balance' => $customer->getAccountBalance(),
            'credit_limit' => $customer->getCreditLimit(),
          ],
          'period' => [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
          ],
          'summary' => [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'overdue_amount' => $overdueAmount,
            'invoice_count' => count($invoices),
            'payment_count' => count($payments),
          ],
          'invoices' => array_map(fn($i) => [
            'id' => $i->getId(),
            'invoice_number' => $i->getInvoiceNumber(),
            'date' => $i->getInvoiceDate()->format('Y-m-d'),
            'due_date' => $i->getDueDate()->format('Y-m-d'),
            'total' => $i->getTotalAmount(),
            'amount_due' => $i->getAmountDue(),
            'status' => $i->getStatus(),
          ], $invoices),
          'payments' => array_map(fn($p) => [
            'id' => $p->getId(),
            'payment_number' => $p->getPaymentNumber(),
            'date' => $p->getPaymentDate()->format('Y-m-d'),
            'amount' => $p->getAmount(),
            'method' => $p->getPaymentMethod(),
            'status' => $p->getStatus(),
          ], $payments),
        ],
        message: 'Statement generated successfully.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to generate statement: ' . $e->getMessage()],
        message: 'Statement generation failed.',
        metadata: ['exception' => get_class($e)]
      );
    }
    }
  
  /**
   * Generate an aging report across all customers.
   *
   * Groups outstanding invoices into standard aging buckets (0–30, 31–60,
   * 61–90, and >90 days past due) and aggregates totals by customer.
   *
   * * @return ServiceResponse Returns summary totals and per-customer breakdown
   */
  public function getAgingReport(): ServiceResponse
  {
    try {
      $now = new \DateTime();
      $invoices = $this->invoiceRepo->findBy(['status' => ['sent', 'overdue', 'partial']]);
      
      $aging = [
        'current' => 0.0,      // 0-30 days
        'days_31_60' => 0.0,
        'days_61_90' => 0.0,
        'over_90' => 0.0,
      ];
      
      $customerAging = [];
      
      foreach ($invoices as $invoice) {
        $amountDue = (float)$invoice->getAmountDue();
        if ($amountDue <= 0) continue;

        $dueDate = $invoice->getDueDate();
        $daysPastDue = $now->diff($dueDate)->days;
        $isPastDue = $now > $dueDate;
        
        $customerId = $invoice->getCustomer()->getId();
        if (!isset($customerAging[$customerId])) {
          $customerAging[$customerId] = [
            'customer_name' => $invoice->getCustomer()->getName(),
            'customer_id' => $customerId,
            'total_due' => 0.0,
            'current' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'over_90' => 0.0,
          ];
        }
        
        $customerAging[$customerId]['total_due'] += $amountDue;
        
        if (!$isPastDue || $daysPastDue <= 30) {
          $aging['current'] += $amountDue;
                    $customerAging[$customerId]['current'] += $amountDue;
        } elseif ($daysPastDue <= 60) {
          $aging['days_31_60'] += $amountDue;
          $customerAging[$customerId]['days_31_60'] += $amountDue;
        } elseif ($daysPastDue <= 90) {
          $aging['days_61_90'] += $amountDue;
          $customerAging[$customerId]['days_61_90'] += $amountDue;
        } else {
          $aging['over_90'] += $amountDue;
          $customerAging[$customerId]['over_90'] += $amountDue;
        }
      }
      
      $totalDue = array_sum($aging);
      
      return ServiceResponse::success(
        data: [
          'summary' => [
            'total_due' => $totalDue,
            'current' => $aging['current'],
            'days_31_60' => $aging['days_31_60'],
            'days_61_90' => $aging['days_61_90'],
            'over_90' => $aging['over_90'],
          ],
          'by_customer' => array_values($customerAging),
          'generated_at' => $now->format('Y-m-d H:i:s'),
        ],
        message: 'Aging report generated successfully.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to generate aging report: ' . $e->getMessage()],
        message: 'Aging report generation failed.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Evaluate whether a customer can place a new order within their credit limit.
   *
   * Compares the customer's account balance to their credit limit and the
   * proposed order amount to determine purchasing eligibility.
   *
   * * @param Customer $customer Customer entity to evaluate
   * * @param float $orderAmount Proposed order amount
   * * @return ServiceResponse Returns eligibility details and remaining credit
   */
  public function checkCustomerCredit(Customer $customer, float $orderAmount): ServiceResponse
  {
    try {
      $creditLimit = (float)($customer->getCreditLimit() ?? 0);
      $currentBalance = (float)$customer->getAccountBalance();
      $availableCredit = $creditLimit - $currentBalance;
      
      $canPurchase = $creditLimit === 0 || $availableCredit >= $orderAmount;
      
      return ServiceResponse::success(
        data: [
          'can_purchase' => $canPurchase,
          'credit_limit' => $creditLimit,
          'current_balance' => $currentBalance,
          'available_credit' => max(0, $availableCredit),
          'order_amount' => $orderAmount,
          'remaining_after' => $canPurchase ? ($availableCredit - $orderAmount) : null,
        ],
        message: $canPurchase ? 'Credit check passed.' : 'Insufficient credit available.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to check customer credit: ' . $e->getMessage()],
        message: 'Credit check failed.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Generate a sequential invoice number.
   *
   * Uses the highest existing invoice ID to compute the next available number,
   * formatted as `INV-YYYY-XXXXXX`.
   *
   * * @return string Formatted invoice number
   */
  private function generateInvoiceNumber(): string
  {
    $latest = $this->invoiceRepo->createQueryBuilder('i')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    
    $nextId = $latest ? ($latest->getId() + 1) : 1;
    return 'INV-' . date('Y') . '-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
  }

  /**
   * Generate a sequential payment number.
   *
   * Uses the highest existing payment ID to compute the next available number,
   * formatted as `PAY-YYYY-XXXXXX`.
   *
   * * @return string Formatted payment number
   */
  private function generatePaymentNumber(): string
  {
    $latest = $this->paymentRepo->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextId = $latest ? ($latest->getId() + 1) : 1;
        return 'PAY-' . date('Y') . '-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
  }
}
