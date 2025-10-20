<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\Account;
use App\Katzen\Repository\LedgerEntryRepository;
use App\Katzen\Service\Response\ServiceResponse;

/**                                                                                                                                                                                                
 * The GeneralLedger service organizes access to read operations on LedgerEntries
 * for the purposes of:
 *   (i) Calculating balances
 *   (ii) Reporting on financial activities
 *
 * Refer to the core AccountingService for other needs.
 */
class GeneralLedger
{
    public function __construct(
        private LedgerEntryRepository $entries,
    ) {}
    
    public function getBalance(Account $account, ?\DateTimeInterface $asOf = null): float
    {
        return $this->entries->sumBalance($account, $asOf);
    }
    
    public function getTrialBalance(\DateTimeInterface $date): array
    {
        return $this->entries->trialBalance($date);
    }
    
    public function getEntries(array $filters): array
    {
        return $this->entries->findByFilters($filters);
    }
}
