<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Account;
use App\Katzen\Repository\AccountRepository;

final class ChartOfAccountsService
{
  public function __construct(private AccountRepository $accounts) {}
  
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
}
