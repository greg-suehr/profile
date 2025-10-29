<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Service\Accounting\ChartOfAccountsService;
use App\Katzen\Service\Accounting\JournalEvent;

final class JournalEventService
{
  private array $templates;
  
  public function __construct(
    private ChartOfAccountsService $coa
  )
  {
    $this->templates = [
      // Order-To-Cash (OTC) Journal Event Cycle
      
      // 1) Order Prepayment and customer deposits
      // Inputs: prepayment, tax_prepaid (usually 0), memo
      'order_prepayment' => new JournalEvent(
        transactionType: 'prepayment',
        rules: [
          ['account' => '1000', 'name' => 'CASH',              'side'=>'debit',  'expr'=>'${prepayment}', 'memo'=>'Customer prepayment'],
          ['account' => '2300', 'name' => 'CUSTOMER_DEPOSITS', 'side'=>'credit', 'expr'=>'${prepayment}', 'memo'=>'Liability for unearned revenue'],
        ]
      ),
      
      // 2) COGS at fulfillment
      // Inputs: cogs_total
      'cogs_on_fulfillment' => new JournalEvent(
        transactionType: 'cogs',
        rules: [
          ['account' => '5100', 'name' => 'COGS_INGREDIENTS', 'side'=>'debit',  'expr'=>'${cogs_total}', 'memo'=>'COGS'],
          ['account' => '1400', 'name' => 'INVENTORY',        'side'=>'credit', 'expr'=>'${cogs_total}', 'memo'=>'Inventory relief'],
        ]
      ),
      
      // 3) Recognize revenue at fulfillment
      // Inputs: revenue, tax_total, shipping_revenue, tip_total
      'unbilled_ar_on_fulfillment' => new JournalEvent(
        transactionType: 'revenue',
        rules: [
          // Gross receivable (unbilled)
          ['account' => '1320', 'name' => 'UNBILLED_AR',      'side'=>'debit',  'expr'=>'${revenue} + ${tax_total} + ${shipping_revenue} + ${tip_total}', 'memo'=>'Recognize receivable at fulfillment'],
                      
          // Revenue and liabilities
          ['account' => '4100', 'name' => 'FOOD_SALES',       'side'=>'credit', 'expr'=>'${revenue}', 'memo'=>'Food revenue'],

          // optional components
          ['account' => '4180', 'name' => 'SHIPPING_INCOME',  'side'=>'credit', 'expr'=>'${shipping_revenue}', 'memo'=>'Shipping income', 'when'=>'${shipping_revenue} != 0'],
          ['account' => '2400', 'name' => 'SALES_TAX_PAY',    'side'=>'credit', 'expr'=>'${tax_total}',        'memo'=>'Sales tax liability', 'when'=>'${tax_total} != 0'],
          ['account' => '2450', 'name' => 'TIPS_PAYABLE',     'side'=>'credit', 'expr'=>'${tip_total}',        'memo'=>'Tips payable', 'when'=>'${tip_total} != 0'],
        ]
      ),

      // 4) Reclass Unbilled AR to Open AR at invoicing
      // Inputs: invoice_total (revenue+tax+shipping+tip), apply_prepayment (<= invoice_total)
      'invoice_reclass_unbilled_to_ar' => new JournalEvent(
        transactionType: 'reclass',
        rules: [
          // move Unbilled AR -> AR
          ['account' => '1100', 'name' => 'ACCOUNTS_RECEIVABLE', 'side'=>'debit',  'expr'=>'${invoice_total}', 'memo'=>'Create AR'],
          ['account' => '1320', 'name' => 'UNBILLED_AR',         'side'=>'credit', 'expr'=>'${invoice_total}', 'memo'=>'Clear Unbilled AR'],
          
          // Optional: apply prior deposits to reduce AR (Dr Customer Deposits, Cr AR)
          ['account' => '2300', 'name' => 'CUSTOMER_DEPOSITS',   'side'=>'debit',  'expr'=>'${apply_prepayment}', 'memo'=>'Apply deposit', 'when'=>'${apply_prepayment} != 0'],
          ['account' => '1100', 'name' => 'ACCOUNTS_RECEIVABLE', 'side'=>'credit', 'expr'=>'${apply_prepayment}', 'memo'=>'Reduce AR by deposit', 'when'=>'${apply_prepayment} != 0'],
        ]
      ),

      // 1) Order Prepayment and customer deposits
      // Inputs: prepayment, tax_prepaid (usually 0), memo
      'invoice_payment' => new JournalEvent(
        transactionType: 'payment',
        rules: [
          ['account' => '1000', 'name' => 'CASH',                'side'=>'debit',  'expr'=>'${amount}', 'memo'=>'Customer payment'],
          ['account' => '1100', 'name' => 'ACCOUNTS_RECEIVABLE', 'side'=>'credit', 'expr'=>'${amount}', 'memo'=>'Reduce AR by payment']
        ]
      ),
      
      // 7) Inventory Spoilage and Waste
      // Inputs: spoilage_cost
      'inventory_spoilage' => new JournalEvent(
        transactionType: 'adjustment',
        rules: [
          ['account' => '5200', 'name' => 'WASTE_SPOILAGE', 'side'=>'debit',  'expr'=>'${spoilage_cost}', 'memo'=>'Spoilage'],
          ['account' => '1400', 'name' => 'INVENTORY',      'side'=>'credit', 'expr'=>'${spoilage_cost}', 'memo'=>'Inventory write-down'],
        ]
      ),

      // 8) Refunds (post-fulfillment):
      // If goods not returned: refund cash and book contra-revenue; reverse tax and tips liabilities.
      // If goods returned & resellable, also reverse COGS/Inventory (optional flag).
      // Inputs: refund_amount, tax_refund, tip_refund, shipping_refund, reverse_cogs (0/1), cogs_reversal
      'refund' => new JournalEvent(
        transactionType: 'refund',
        rules: [
          ['account' => '4500', 'name' => 'SALES_RETURNS',   'side'=>'debit',  'expr'=>'${refund_amount}', 'memo'=>'Contra revenue'],
          ['account' => '2400', 'name' => 'SALES_TAX_PAY',   'side'=>'debit',  'expr'=>'${tax_refund}',    'memo'=>'Reverse tax liability', 'when'=>'${tax_refund} != 0'],          
          ['account' => '2450', 'name' => 'TIPS_PAYABLE',    'side'=>'debit',  'expr'=>'${tip_refund}',    'memo'=>'Return tips liability', 'when'=>'${tip_refund} != 0'],
          ['account' => '4180', 'name' => 'SHIPPING_INCOME', 'side'=>'debit',  'expr'=>'${shipping_refund}','memo'=>'Reverse shipping income', 'when'=>'${shipping_refund} != 0'],
          
            ['account' => '1000', 'name' => 'CASH',            'side'=>'credit', 'expr'=>'${refund_amount} + ${tax_refund} + ${tip_refund} + ${shipping_refund}', 'memo'=>'Cash out'],

          // Optional inventory put-back
          ['account' => '1400', 'name' => 'INVENTORY',       'side'=>'debit',  'expr'=>'${cogs_reversal}', 'memo'=>'Inventory returned', 'when'=>'${reverse_cogs} != 0'],
          ['account' => '5100', 'name' => 'COGS_INGREDIENTS','side'=>'credit', 'expr'=>'${cogs_reversal}', 'memo'=>'Reverse COGS', 'when'=>'${reverse_cogs} != 0'],
        ]
      ),

      // Procure-To-Pay (PTP) Journal Event Cycle
      
      // 1) Stock Receipt
      // Inputs: receipt_total
      'stock_receipt' => new JournalEvent(
        transactionType: 'stock_receipt',
        rules: [
          ['account' => '1400', 'name' => 'INVENTORY',      'side'=>'debit',  'expr'=>'${receipt_total}'],
          ['account' => '1350', 'name' => 'GOODS_RECEIVED_NOT_INVOICED','side'=>'credit', 'expr'=>'${receipt_total}'],
        ]
      ),

      // 2. Invoice Match, Cost Realization
      // Inputs: gr_total, invoice_total, variance
      'vendor_invoice_matched' => new JournalEvent(
        transactionType: 'invoice_match',
        rules: [
          ['account' => '1350', 'name' => 'GOODS_RECEIVED_NOT_INVOICED','side'=>'debit',  'expr'=>'${gr_total}'],
          ['account' => '2100', 'name' => 'ACCOUNTS_PAYABLE','side'=>'credit', 'expr'=>'${invoice_total}'],
          ['account' => '5300', 'name' => 'PURCHASE_VARIANCE','side'=>'debit', 'expr'=>'${variance}', 'when'=>'${variance} != 0'],
        ]
      ),

      // 3. Vendor Payment
      // Inputs: amount
      'vendor_payment' => new JournalEvent(
        transactionType: 'payment',
        rules: [
          ['account' => '2100', 'name' => 'ACCOUNTS_PAYABLE', 'side'=>'debit',  'expr'=>'${amount}'],
          ['account' => '1000', 'name' => 'CASH',             'side'=>'credit', 'expr'=>'${amount}'],
        ]
      ),
    ];
  }

  public function get(string $name): JournalEvent
  {
    $tpl = $this->templates[$name] ?? null;
    if (!$tpl) throw new \RuntimeException("Unknown JournalEvent template: {$name}");
    return $tpl;
  }
}
