<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\VendorCreditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorCreditRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_vendor_credit', columns: ['vendor_id', 'credit_number'])]
#[ORM\Index(name: 'idx_vendor_credit_status', columns: ['status'])]
class VendorCredit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'vendor_credits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vendor $vendor = null;

    #[ORM\Column(length: 100)]
    private ?string $credit_number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $credit_date = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $credit_amount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount_applied = '0.00';

    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    private ?VendorInvoice $original_invoice = null;

    #[ORM\ManyToOne]
    private ?StockReceipt $stock_receipt = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'open'; // open, partial, applied, void

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, VendorInvoice>
     */
    #[ORM\ManyToMany(targetEntity: VendorInvoice::class, inversedBy: 'applied_credits')]
    #[ORM\JoinTable(name: 'vendor_credit_application')]
    private Collection $applied_to_invoices;

    public function __construct()
    {
        $this->applied_to_invoices = new ArrayCollection();
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->vendor?->getName() ?? 'Unknown', $this->credit_number ?? 'Draft');
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVendor(): ?Vendor
    {
        return $this->vendor;
    }

    public function setVendor(?Vendor $vendor): static
    {
        $this->vendor = $vendor;
        return $this;
    }

    public function getCreditNumber(): ?string
    {
        return $this->credit_number;
    }

    public function setCreditNumber(string $credit_number): static
    {
        $this->credit_number = $credit_number;
        return $this;
    }

    public function getCreditDate(): ?\DateTimeInterface
    {
        return $this->credit_date;
    }

    public function setCreditDate(\DateTimeInterface $credit_date): static
    {
        $this->credit_date = $credit_date;
        return $this;
    }

    public function getCreditAmount(): ?string
    {
        return $this->credit_amount;
    }

    public function setCreditAmount(string $credit_amount): static
    {
        $this->credit_amount = $credit_amount;
        return $this;
    }

    public function getAmountApplied(): ?string
    {
        return $this->amount_applied;
    }

    public function setAmountApplied(string $amount_applied): static
    {
        $this->amount_applied = $amount_applied;
        $this->updateStatus();
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getOriginalInvoice(): ?VendorInvoice
    {
        return $this->original_invoice;
    }

    public function setOriginalInvoice(?VendorInvoice $original_invoice): static
    {
        $this->original_invoice = $original_invoice;
        return $this;
    }

    public function getStockReceipt(): ?StockReceipt
    {
        return $this->stock_receipt;
    }

    public function setStockReceipt(?StockReceipt $stock_receipt): static
    {
        $this->stock_receipt = $stock_receipt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    /**
     * @return Collection<int, VendorInvoice>
     */
    public function getAppliedToInvoices(): Collection
    {
        return $this->applied_to_invoices;
    }

    public function addAppliedToInvoice(VendorInvoice $invoice): static
    {
        if (!$this->applied_to_invoices->contains($invoice)) {
            $this->applied_to_invoices->add($invoice);
        }
        return $this;
    }

    public function removeAppliedToInvoice(VendorInvoice $invoice): static
    {
        $this->applied_to_invoices->removeElement($invoice);
        return $this;
    }

    // ============================================
    // BUSINESS LOGIC
    // ============================================

    public function getRemainingBalance(): float
    {
        $total = (float)$this->credit_amount;
        $applied = (float)$this->amount_applied;
        return max(0, $total - $applied);
    }

    public function applyToInvoice(VendorInvoice $invoice, float $amount): void
    {
        if ($amount > $this->getRemainingBalance()) {
            throw new \LogicException('Cannot apply more than remaining credit balance');
        }
        
        if (!$this->applied_to_invoices->contains($invoice)) {
            $this->applied_to_invoices->add($invoice);
        }
        
        $currentApplied = (float)$this->amount_applied;
        $this->amount_applied = number_format($currentApplied + $amount, 2, '.', '');
        $this->updateStatus();
        $this->updated_at = new \DateTime();
    }

    private function updateStatus(): void
    {
        $remaining = $this->getRemainingBalance();
        
        if ($remaining <= 0.01) {
            $this->status = 'applied';
        } elseif ((float)$this->amount_applied > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'open';
        }
    }

    public function void(): void
    {
        $this->status = 'void';
        $this->updated_at = new \DateTime();
    }
}
