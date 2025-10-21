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
    ['code' => '1400', 'name' => 'Inventory', 'type' => 'asset'],
    
    // Liabilities (2000-2999)
    ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
    ['code' => '2300', 'name' => 'Customer Deposits', 'type' => 'liability'],
    ['code' => '2400', 'name' => 'Sales Tax Payable', 'type' => 'liability'],
    ['code' => '2450', 'name' => 'Tips Payable', 'type' => 'liability'],
    
    // Equity (3000-3999) - Added for completeness
    ['code' => '3000', 'name' => "Owner's Equity", 'type' => 'equity'],
    ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity'],
    
    // Revenue (4000-4999)
    ['code' => '4100', 'name' => 'Food Sales', 'type' => 'revenue'],
    ['code' => '4180', 'name' => 'Shipping Income', 'type' => 'revenue'],
    ['code' => '4500', 'name' => 'Sales Returns & Allowances', 'type' => 'revenue_contra'],
    
    // Expenses (5000-5999)
    ['code' => '5100', 'name' => 'Cost of Goods Sold - Ingredients', 'type' => 'expense'],
    ['code' => '5200', 'name' => 'Waste & Spoilage', 'type' => 'expense'],
    ['code' => '5300', 'name' => 'Purchase Price Variance', 'type' => 'expense'],
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
