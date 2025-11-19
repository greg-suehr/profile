<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Account;
use App\Katzen\Repository\AccountRepository;
use App\Katzen\Repository\LedgerEntryLineRepository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds financial Accounts by code or name, orchestrates balance calculations and
 * balance cache management, and encodes formatting rules for displaying accounts.
 */
final class ChartOfAccountsService
{
  private const TYPE_ORDER = [
    'asset' => 1,
    'asset_contra' => 2,
    'liability' => 3,
    'liability_contra' => 4,
    'equity' => 5,
    'equity_contra' => 6,
    'revenue' => 7,
    'revenue_contra' => 8,
    'expense' => 9,
  ];

  private const TYPE_LABELS = [
    'asset' => 'Assets',
    'asset_contra' => 'Contra-Assets',
    'liability' => 'Liabilities',
    'liability_contra' => 'Contra-Liabilities',
    'equity' => 'Equity',
    'equity_contra' => 'Contra-Equity',
    'revenue' => 'Revenue',
    'revenue_contra' => 'Contra-Revenue',
    'expense' => 'Expenses',
  ];
  
  public function __construct(
    private AccountRepository $accounts,
    private LedgerEntryLineRepository $ledgerLineRepo,
    private EntityManagerInterface $em,
  )
  {}
  
  /**
   * Resolve a dot-path or code to an Account entity.
   */
  public function resolve(string $key): Account
  {
    $acc = $this->accounts->findOneBy(['code' => $key]) 
            ?? $this->accounts->findOneBy(['name' => $key]);
    if (!$acc) throw new \RuntimeException("Unknown account: {$key}");
    return $acc;
  }

  /**
   * Build the complete chart structure grouped by type with balances.
   * 
   * @param \DateTimeInterface|null $asOf Calculate balances as of this date
   * @param bool $showZeroBalances Include accounts with zero balances
   * @return array Hierarchical structure grouped by account type
   */
  public function buildChartStructure(
    ?\DateTimeInterface $asOf = null,
    bool $showZeroBalances = true
  ): array {
    $accounts = $this->accounts->findBy([], ['code' => 'ASC']);
        
    $balances = $this->calculateAllBalances($accounts, $asOf);
        
    $tree = $this->buildAccountTree($accounts, $balances);
        
    $grouped = $this->groupByType($tree);
        
    if (!$showZeroBalances) {
      $grouped = $this->filterZeroBalances($grouped);
    }
        
    return $grouped;
  }

  /**
   * Calculate balances for all accounts as a batch query.
   */
  private function calculateAllBalances(
    array $accounts,
    ?\DateTimeInterface $asOf = null
  ): array {
    $balances = [];
        
    $qb = $this->em->createQueryBuilder();
    $qb->select('IDENTITY(line.account) as account_id')
       ->addSelect('SUM(line.debit) as total_debits')
       ->addSelect('SUM(line.credit) as total_credits')
       ->from('App\Katzen\Entity\LedgerEntryLine', 'line')
       ->innerJoin('line.entry', 'entry')
       ->groupBy('line.account');
        
    if ($asOf) {
      $qb->andWhere('entry.timestamp <= :asOf')
         ->setParameter('asOf', $asOf);
    }

    $results = $qb->getQuery()->getResult();
        
    foreach ($results as $row) {
      $accountId = $row['account_id'];
      $debits = (float)($row['total_debits'] ?? 0);
      $credits = (float)($row['total_credits'] ?? 0);
      
      $balances[$accountId] = [
        'debits' => $debits,
        'credits' => $credits,
        'balance' => $debits - $credits,
      ];
    }

    foreach ($accounts as $account) {
      if (!isset($balances[$account->getId()])) {
        $balances[$account->getId()] = [
          'debits' => 0.0,
          'credits' => 0.0,
          'balance' => 0.0,
        ];
      }
    }
        
    return $balances;
  }

  /**
   * Build hierarchical tree structure from flat account list.
   */
  private function buildAccountTree(array $accounts, array $balances): array
  {
    $tree = [];
    $lookup = [];

    $node = null;
    foreach ($accounts as $account) {
      $node = $this->createAccountNode($account, $balances);

      $lookup[$account->getId()] = $node;
      
      if (!$account->getParent()) {
        $tree[] = $node;
      }
    }

    foreach ($accounts as $account) {
      if ($parent = $account->getParent()) {
        $parentId = $parent->getId();
        if (isset($lookup[$parentId])) {
          $lookup[$parentId]['children'][] = &$lookup[$account->getId()];
        }
      }
    }

    foreach ($tree as &$node) {
      $this->calculateRollupBalances($node);
    }
    
    return $tree;
  }

  /**
   * Create a node structure for an account with its balance data.
   */
  private function createAccountNode(Account $account, array $balances): array
  {
    $accountId = $account->getId();
    $balanceData = $balances[$accountId] ?? ['debits' => 0, 'credits' => 0, 'balance' => 0];
    
    return [
      'account' => $account,
      'id' => $accountId,
      'code' => $account->getCode(),
      'name' => $account->getName(),
      'type' => $account->getType(),
      'debits' => $balanceData['debits'],
      'credits' => $balanceData['credits'],
      'balance' => $balanceData['balance'],
      'rollup_balance' => $balanceData['balance'],
      'children' => [],
      'has_children' => false,
    ];
  }

  /**
   * Calculate rollup balances recursively (parent includes children).
   * 
   * Returns the rollup balance for the node.
   */
  private function calculateRollupBalances(array &$node): float
  {
    if (empty($node['children'])) {
      $node['has_children'] = false;
      $node['rollup_balance'] = $node['balance'];
      return $node['balance'];
    }
        
    $node['has_children'] = true;
    $childSum = 0;
    
    foreach ($node['children'] as &$child) {
      $childSum += $this->calculateRollupBalances($child);
    }
    
    $node['rollup_balance'] = $node['balance'] + $childSum;
    
    return $node['rollup_balance'];
  }

  /**
   * Group accounts by type for organized display.
   */
  private function groupByType(array $tree): array
  {
    $grouped = [];
        
    foreach ($tree as $node) {
      $type = $node['type'];
      
      if (!isset($grouped[$type])) {
        $grouped[$type] = [
          'type' => $type,
          'label' => self::TYPE_LABELS[$type] ?? ucfirst($type),
          'order' => self::TYPE_ORDER[$type] ?? 99,
          'accounts' => [],
          'total_balance' => 0,
        ];
      }
      
      $grouped[$type]['accounts'][] = $node;
      $grouped[$type]['total_balance'] += $node['rollup_balance'];
    }
    
    usort($grouped, fn($a, $b) => $a['order'] <=> $b['order']);
        
    return $grouped;
  }

  /**
   * Filter out accounts and groups with zero balances.
   */
  private function filterZeroBalances(array $grouped): array
  {
    foreach ($grouped as $typeKey => &$group) {
      $group['accounts'] = array_filter(
        $group['accounts'],
        fn($node) => abs($node['rollup_balance']) > 0.01 // Allow for floating point
      );
      
      $group['total_balance'] = array_sum(
        array_map(fn($node) => $node['rollup_balance'], $group['accounts'])
            );
      
      if (empty($group['accounts'])) {
        unset($grouped[$typeKey]);
      }
    }
    
    return array_values($grouped);
  }

  /**
   * Calculate summary totals for the financial statement.
   */
  public function calculateTotals(array $chartData): array
  {
    $totals = [
      'total_assets' => 0,
      'total_liabilities' => 0,
      'total_equity' => 0,
      'total_revenue' => 0,
      'total_expenses' => 0,
      'net_income' => 0,
      'assets_liabilities_equity' => 0,
    ];

    foreach ($chartData as $group) {
      $balance = $group['total_balance'];
      $type = $group['type'];
      
      // Accumulate by major category
      if (str_starts_with($type, 'asset')) {
        $totals['total_assets'] += $type === 'asset_contra' ? -$balance : $balance;
      } elseif (str_starts_with($type, 'liability')) {
        $totals['total_liabilities'] += $type === 'liability_contra' ? -$balance : $balance;
      } elseif (str_starts_with($type, 'equity')) {
        $totals['total_equity'] += $type === 'equity_contra' ? -$balance : $balance;
      } elseif (str_starts_with($type, 'revenue')) {
        $totals['total_revenue'] += $type === 'revenue_contra' ? $balance : -$balance;
      } elseif ($type === 'expense') {
        $totals['total_expenses'] += $balance;
      }
    }

    $totals['net_income'] = $totals['total_revenue'] - $totals['total_expenses'];
        
    $totals['assets_liabilities_equity'] = 
      $totals['total_liabilities'] + 
      $totals['total_equity'] + 
      $totals['net_income'];
    
    return $totals;
  }

  # TODO: move this to a dedicated export service
  /**
   * Export chart data to CSV format.
   */
  public function exportToCsv(array $chartData, ?\DateTimeInterface $asOf = null): string
  {
    $output = fopen('php://temp', 'r+'); # TODO: not this?
        
    fputcsv($output, [
      'Type',
      'Code',
      'Account Name',
      'Direct Balance',
      'Rollup Balance',
      'As Of Date',
    ]);
    
    $asOfString = $asOf ? $asOf->format('Y-m-d') : 'Current';
    
    foreach ($chartData as $group) {
      fputcsv($output, [
        $group['label'],
        '',
        '',
        '',
        number_format($group['total_balance'], 2),
        $asOfString
      ]);

      foreach ($group['accounts'] as $node) {
        $this->exportNodeToCsv($output, $node, $asOfString, 0);
      }
      
      // Blank line between groups
      fputcsv($output, ['']);
    }
        
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
  }

  /**
   * Recursively export account nodes to CSV with indentation.
   */
  private function exportNodeToCsv($output, array $node, string $asOf, int $level): void
  {
    $indent = str_repeat('  ', $level);
        
    fputcsv($output, [
      '',
      $node['code'],
      $indent . $node['name'],
      number_format($node['balance'], 2),
      number_format($node['rollup_balance'], 2),
      $asOf
    ]);
        
    foreach ($node['children'] as $child) {
      $this->exportNodeToCsv($output, $child, $asOf, $level + 1);
    }
  }

  /**
   * Format balance for display with proper sign and styling.
   */
  public function formatBalance(float $balance, string $type): array
  {
    $isNegative = $balance < 0;
    $isContra = str_ends_with($type, '_contra');
        
    // Determine display sign
    // Normal debit accounts (assets, expenses): positive = normal
    // Normal credit accounts (liabilities, equity, revenue): negative = normal
    // Contra accounts reverse the sign
    
    $displayValue = abs($balance);
    $cssClass = '';
    
    if (str_starts_with($type, 'asset') || $type === 'expense') {
      // Debit-normal accounts
      $showNegative = $isContra ? !$isNegative : $isNegative;
      $cssClass = $showNegative ? 'text-danger' : '';
    } else {
      // Credit-normal accounts
      $showNegative = $isContra ? $isNegative : !$isNegative;
      $cssClass = $showNegative ? 'text-danger' : '';
    }
    
    return [
      'value' => $displayValue,
      'formatted' => number_format($displayValue, 2),
      'is_negative' => $showNegative,
      'css_class' => $cssClass,
    ];
  }
}
