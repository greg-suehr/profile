<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\VendorInvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorInvoiceRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_vendor_invoice', columns: ['vendor_id', 'invoice_number'])]
#[ORM\Index(name: 'idx_vendor_invoice_status', columns: ['status'])]
#[ORM\Index(name: 'idx_vendor_invoice_due_date', columns: ['due_date'])]
#[ORM\HasLifecycleCallbacks]
class VendorInvoice
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'vendor_invoices')]
  #[ORM\JoinColumn(nullable: false)]
  private ?Vendor $vendor = null;
  
  #[ORM\Column(length: 100)]
  private ?string $invoice_number = null;
  
  #[ORM\Column(type: Types::DATE_MUTABLE)]
  private ?\DateTimeInterface $invoice_date = null;

  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $due_date = null;
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $subtotal = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $tax_amount = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $shipping_amount = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $discount_amount = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $total_amount = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $amount_paid = '0.00';
  
  #[ORM\Column(length: 50)]
  private ?string $status = 'draft'; // draft, pending, approved, paid, partial, void
  
  #[ORM\Column(length: 50, nullable: true)]
  private ?string $approval_status = null; // pending, approved, rejected
  
  #[ORM\Column(nullable: true)]
  private ?int $approved_by = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $approved_at = null;
  
  #[ORM\ManyToOne]
  private ?Purchase $purchase = null;
  
  #[ORM\Column]
  private ?bool $reconciled = false;
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $variance_total = '0.00';
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $variance_notes = null;
  
  #[ORM\Column(length: 50)]
  private ?string $source_type = 'manual'; // manual, email, ocr_scan, api
  
  #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
  private ?string $ocr_confidence = null;
  
  #[ORM\Column(length: 500, nullable: true)]
  private ?string $original_file_path = null;
  
  #[ORM\Column]
  private ?int $created_by = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $created_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updated_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $posted_at = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $notes = null;
  
  /**
   * @var Collection<int, VendorInvoiceItem>
   */
  #[ORM\OneToMany(targetEntity: VendorInvoiceItem::class, mappedBy: 'vendor_invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
  private Collection $items;
  
  /**
   * @var Collection<int, StockReceipt>
   */
  #[ORM\ManyToMany(targetEntity: StockReceipt::class)]
  #[ORM\JoinTable(name: 'vendor_invoice_receipt')]
  private Collection $stock_receipts;
  
  /**
   * @var Collection<int, Payment>
   */
  #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'vendor_invoice')]
  private Collection $payments;
  
  /**
   * @var Collection<int, VendorCredit>
   */
  #[ORM\ManyToMany(targetEntity: VendorCredit::class, mappedBy: 'applied_to_invoices')]
  private Collection $applied_credits;
  
  public function __construct()
  {
    $this->items = new ArrayCollection();
    $this->stock_receipts = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->applied_credits = new ArrayCollection();
    $this->created_at = new \DateTime();
    $this->updated_at = new \DateTime();
  }
  
  public function __toString(): string
  {
    return sprintf('%s - %s', $this->vendor?->getName() ?? 'Unknown', $this->invoice_number ?? 'Draft');
  }
  
  public function getId(): ?int { return $this->id; }
  public function getVendor(): ?Vendor { return $this->vendor; }
  public function setVendor(?Vendor $vendor): static
  {
    $this->vendor = $vendor;
    return $this;
  }
  public function getInvoiceNumber(): ?string { return $this->invoice_number; }
  public function setInvoiceNumber(string $invoice_number): static
  {
    $this->invoice_number = $invoice_number;
    return $this;
  }
  
  public function getInvoiceDate(): ?\DateTimeInterface
  {
    return $this->invoice_date;
  }

  public function setInvoiceDate(\DateTimeInterface $invoice_date): static
  {
    $this->invoice_date = $invoice_date;
    return $this;
  }

  public function getDueDate(): ?\DateTimeInterface
  {
    return $this->due_date;
  }

  public function setDueDate(?\DateTimeInterface $due_date): static
  {
     $this->due_date = $due_date;
     return $this;
  }

  public function getSubtotal(): ?string
  {
    return $this->subtotal;
  }

  public function setSubtotal(string $subtotal): static
  {
    $this->subtotal = $subtotal;
    return $this;
  }

  public function getTaxAmount(): ?string
  {
    return $this->tax_amount;
  }

  public function setTaxAmount(string $tax_amount): static
  {
    $this->tax_amount = $tax_amount;
    return $this;
  }

  public function getShippingAmount(): ?string
  {
    return $this->shipping_amount;
  }

  public function setShippingAmount(string $shipping_amount): static
  {
    $this->shipping_amount = $shipping_amount;
    return $this;
  }

  public function getDiscountAmount(): ?string
  {
    return $this->discount_amount;
  }

  public function setDiscountAmount(string $discount_amount): static
  {
    $this->discount_amount = $discount_amount;
    return $this;
  }

  public function getTotalAmount(): ?string
  {
    return $this->total_amount;
  }

  public function setTotalAmount(string $total_amount): static
  {
    $this->total_amount = $total_amount;
    return $this;
  }

  public function getAmountPaid(): ?string
  {
    return $this->amount_paid;
  }

  public function setAmountPaid(string $amount_paid): static
  {
    $this->amount_paid = $amount_paid;
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

  public function getApprovalStatus(): ?string
  {
    return $this->approval_status;
  }

  public function setApprovalStatus(?string $approval_status): static
  {
    $this->approval_status = $approval_status;
    return $this;
  }

  public function getApprovedBy(): ?int
  {
    return $this->approved_by;
  }

  public function setApprovedBy(?int $approved_by): static
  {
    $this->approved_by = $approved_by;
    return $this;
  }

  public function getApprovedAt(): ?\DateTimeInterface
  {
    return $this->approved_at;
  }

  public function setApprovedAt(?\DateTimeInterface $approved_at): static
  {
    $this->approved_at = $approved_at;
    return $this;
  }

  public function getPurchase(): ?Purchase
  {
    return $this->purchase;
  }

  public function setPurchase(?Purchase $purchase): static
  {
    $this->purchase = $purchase;
    return $this;
  }

  public function isReconciled(): ?bool
  {
    return $this->reconciled;
  }

  public function setReconciled(bool $reconciled): static
  {
    $this->reconciled = $reconciled;
    return $this;
  }

  public function getVarianceTotal(): ?string
  {
    return $this->variance_total;
  }

  public function setVarianceTotal(string $variance_total): static
  {
    $this->variance_total = $variance_total;
    return $this;
  }

  public function getVarianceNotes(): ?string
  {
    return $this->variance_notes;
  }

  public function setVarianceNotes(?string $variance_notes): static
  {
    $this->variance_notes = $variance_notes;
    return $this;
  }

  public function getSourceType(): ?string
  {
    return $this->source_type;
  }

  public function setSourceType(string $source_type): static
  {
    $this->source_type = $source_type;
    return $this;
  }

    public function getOcrConfidence(): ?string
    {
        return $this->ocr_confidence;
    }

    public function setOcrConfidence(?string $ocr_confidence): static
    {
        $this->ocr_confidence = $ocr_confidence;
        return $this;
    }

    public function getOriginalFilePath(): ?string
    {
        return $this->original_file_path;
    }

    public function setOriginalFilePath(?string $original_file_path): static
    {
        $this->original_file_path = $original_file_path;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->created_by;
    }

    public function setCreatedBy(int $created_by): static
    {
        $this->created_by = $created_by;
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

    public function getPostedAt(): ?\DateTimeInterface
    {
        return $this->posted_at;
    }

    public function setPostedAt(?\DateTimeInterface $posted_at): static
    {
        $this->posted_at = $posted_at;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * @return Collection<int, VendorInvoiceItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(VendorInvoiceItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setVendorInvoice($this);
        }
        return $this;
    }

    public function removeItem(VendorInvoiceItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getVendorInvoice() === $this) {
                $item->setVendorInvoice(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, StockReceipt>
     */
    public function getStockReceipts(): Collection
    {
        return $this->stock_receipts;
    }

    public function addStockReceipt(StockReceipt $receipt): static
    {
        if (!$this->stock_receipts->contains($receipt)) {
            $this->stock_receipts->add($receipt);
        }
        return $this;
    }

    public function removeStockReceipt(StockReceipt $receipt): static
    {
        $this->stock_receipts->removeElement($receipt);
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setVendorInvoice($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getVendorInvoice() === $this) {
                $payment->setVendorInvoice(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, VendorCredit>
     */
    public function getAppliedCredits(): Collection
    {
        return $this->applied_credits;
    }

    public function addAppliedCredit(VendorCredit $credit): static
    {
        if (!$this->applied_credits->contains($credit)) {
            $this->applied_credits->add($credit);
            $credit->addAppliedToInvoice($this);
        }
        return $this;
    }

    public function removeAppliedCredit(VendorCredit $credit): static
    {
        if ($this->applied_credits->removeElement($credit)) {
            $credit->removeAppliedToInvoice($this);
        }
        return $this;
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += (float)$item->getLineTotal();
        }
        
        $this->subtotal = number_format($subtotal, 2, '.', '');
        
        $total = $subtotal 
            + (float)$this->tax_amount 
            + (float)$this->shipping_amount 
            - (float)$this->discount_amount;
            
        $this->total_amount = number_format($total, 2, '.', '');
        $this->updated_at = new \DateTime();
    }

    public function getAmountDue(): float
    {
        $total = (float)$this->total_amount;
        $paid = (float)$this->amount_paid;
        $creditApplied = 0.0;
        
        foreach ($this->applied_credits as $credit) {
            // TODO: add a VendorCreditApplication mapping table to track amount per invoice
            $creditApplied += (float)$credit->getAmountApplied();
        }
        
        return max(0, $total - $paid - $creditApplied);
    }

    public function isPaid(): bool
    {
        return $this->getAmountDue() <= 0.01;
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->isPaid()) {
            return false;
        }
        
        return $this->due_date < new \DateTime();
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        $now = new \DateTime();
        return $now->diff($this->due_date)->days;
    }

    public function post(): void
    {
        if ($this->status !== 'draft') {
            throw new \LogicException('Only draft invoices can be posted');
        }
        
        $this->status = 'pending';
        $this->posted_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function approve(int $userId): void
    {
        $this->approval_status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = new \DateTime();
        $this->status = 'approved';
        $this->updated_at = new \DateTime();
    }

    public function void(): void
    {
        $this->status = 'void';
        $this->updated_at = new \DateTime();
    }

    public function markPaid(float $amount): void
    {
        $currentPaid = (float)$this->amount_paid;
        $newPaid = $currentPaid + $amount;
        $this->amount_paid = number_format($newPaid, 2, '.', '');
        
        if ($this->isPaid()) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }
        
        $this->updated_at = new \DateTime();
    }
}
