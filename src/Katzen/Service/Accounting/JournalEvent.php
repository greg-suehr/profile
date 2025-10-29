<?php

namespace App\Katzen\Service\Accounting;

final class JournalEvent
{
  public function __construct(
    private string $transactionType,
    private array $rules,
  )
  {}

  public function getTransactionType(): string
  {
    return $this->transactionType;
  }

  /**
   * Build LedgerEntryLine data from template rules and amounts
   * 
   * @param array $amounts Business values (e.g., prepayment, tax_total)
   * @param array $metadata Additional context
   * @return array Protocol LedgerEntryLines ready for database
   */
  public function buildLines(array $amounts, array $metadata = []): array
  {
    $lines = [];

    foreach ($this->rules as $rule) {

      if (isset($rule['when'])) {
        if (!$this->evaluateExpression($rule['when'], $amounts)) {
          continue;
        }
      }
            
      $amount = $this->evaluateExpression($rule['expr'], $amounts);
      
      if ($amount == 0) {
        continue; // Skip zero-value lines
      }
            
      $side = $rule['side'] ?? 'debit';
      
      $lines[] = [
        'account' => $rule['account'],
        'debit' => $side === 'debit' ? (string)$amount : null,
        'credit' => $side === 'credit' ? (string)$amount : null,
        'memo' => $rule['memo'] ?? null,
      ];
    }

    return $lines;
  }

  /**
   * Simple expression evaluator for template expressions like "${prepayment} + ${tax_total}"
   */
  private function evaluateExpression(string $expr, array $amounts): float
  {
    if (!empty($amounts) && isset($amounts[0]) && is_array($amounts[0]) && array_key_exists('expr_key', $amounts[0])) {
      $amounts = array_column($amounts, 'amount', 'expr_key');
    }

    $expr = preg_replace_callback('/\$\{(\w+)\}/', function($m) use ($amounts) {
        return $amounts[$m[1]] ?? '0';
    }, $expr);

    // TODO: use symfony/expression-language for safe template evaluation
    return (float) @eval("return {$expr};");
  }
}
