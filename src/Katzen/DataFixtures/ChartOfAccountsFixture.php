<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ChartOfAccountsFixture extends Fixture
{
  private const ACCOUNTS = [
    // Assets (1000-1999)
    ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
    ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
    ['code' => '1320', 'name' => 'Unbilled Accounts Receivable', 'type' => 'asset'],
    ['code' => '1350', 'name' => 'Goods Received Not Invoiced', 'type' => 'liability_contra'],
    ['code' => '1400', 'name' => 'Inventory', 'type' => 'asset'],
    ['code' => '1500', 'name' => 'Prepaid Expenses', 'type' => 'asset'],
    ['code' => '1600', 'name' => 'Fixed Assets', 'type' => 'asset'],
    ['code' => '1650', 'name' => 'Accumulated Depreciation', 'type' => 'asset_contra'],
    ['code' => '1700', 'name' => 'Security Deposits', 'type' => 'asset' ],
    
    // Liabilities (2000-2999)
    ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
    ['code' => '2300', 'name' => 'Customer Deposits', 'type' => 'liability'],
    ['code' => '2400', 'name' => 'Sales Tax Payable', 'type' => 'liability'],
    ['code' => '2450', 'name' => 'Tips Payable', 'type' => 'liability'],
    ['code' => '2500', 'name' => 'Accrued Expenses', 'type' => 'liability'],
    ['code' => '2600', 'name' => 'Notes Payable', 'type' => 'liability'],
    ['code' => '2700', 'name' => 'Payroll Liabilities', 'type' => 'liability'],
    
    // Equity (3000-3999)
    ['code' => '3000', 'name' => "Owner's Equity", 'type' => 'equity'],
    ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity'],
    ['code' => '3200', 'name' => "Owner's Draw", 'type' => 'equity_contra'],
    ['code' => '3300', 'name' => "Owner's Contributions", 'type' => 'equity'],
    
    // Revenue (4000-4999)
    ['code' => '4100', 'name' => 'Food Sales', 'type' => 'revenue'],
    ['code' => '4180', 'name' => 'Shipping Income', 'type' => 'revenue'],
    ['code' => '4200', 'name' => 'Catering Income', 'type' => 'revenue'],
    ['code' => '4300', 'name' => 'Other Operating Income', 'type' => 'revenue'],
    ['code' => '4500', 'name' => 'Sales Returns & Allowances', 'type' => 'revenue_contra'],
    
    // Expenses - Direct Costs (5000-5999)
    ['code' => '5100', 'name' => 'Cost of Goods Sold - Ingredients', 'type' => 'expense'],
    ['code' => '5200', 'name' => 'Waste & Spoilage', 'type' => 'expense'],
    ['code' => '5300', 'name' => 'Purchase Price Variance', 'type' => 'expense'],

    // Expenses - Indirect Costs (6000-6999)
    ['code' => '6000', 'name' => 'Rent Expense', 'type' => 'expense'],
    ['code' => '6100', 'name' => 'Utilities Expense', 'type' => 'expense'],
    ['code' => '6200', 'name' => 'Payroll Expense', 'type' => 'expense'],
    ['code' => '6250', 'name' => 'Payroll Taxes', 'type' => 'expense'],
    ['code' => '6300', 'name' => 'Commissions or Contract Labor', 'type' => 'expense'],
    ['code' => '6400', 'name' => 'Marketing & Advertising', 'type' => 'expense'],
    ['code' => '6500', 'name' => 'Insurance Expense', 'type' => 'expense'],
    ['code' => '6600', 'name' => 'Depreciation Expense', 'type' => 'expense'],
    ['code' => '6700', 'name' => 'Office Supplies & Admin', 'type' => 'expense'],
    ['code' => '6800', 'name' => 'Repairs & Maintenance', 'type' => 'expense'],
    ['code' => '6900', 'name' => 'Miscellaneous', 'type' => 'expense'],    
  ];

  public function load(ObjectManager $manager): void
  {
    foreach (self::ACCOUNTS as $accountData) {
      $existing = $manager->getRepository(Account::class)
                ->findOneBy(['code' => $accountData['code']]);
            
      if ($existing) {
        $existing->setName($accountData['name']);
        $existing->setType($accountData['type']);
      } else {
        $account = new Account();
        $account->setCode($accountData['code']);
        $account->setName($accountData['name']);
        $account->setType($accountData['type']);
        $manager->persist($account);
      }
    }
        
    $manager->flush();
  }
}
