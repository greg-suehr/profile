<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\VendorInvoiceItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorInvoiceItemRepository::class)]
#[ORM\Index(name: 'idx_vendor_invoice_item_invoice', columns: ['vendor_invoice_id'])]
#[ORM\Index(name: 'idx_vendor_invoice_item_stock_target', columns: ['stock_target_id'])]
#[ORM\Index(name: 'idx_vendor_invoice_item_expense_account', columns: ['expense_account_id'])]
#[ORM\HasLifecycleCallbacks]
class VendorInvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VendorInvoice $vendor_invoice = null;

    #[ORM\ManyToOne]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private ?string $quantity = '0.000';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit_of_measure = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $unit_price = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $line_total = '0.00';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $expense_account = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cost_center = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $expected_unit_price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $price_variance_pct = null;

    #[ORM\Column]
    private ?bool $variance_flagged = false;

    #[ORM\ManyToOne]
    private ?StockReceiptItem $stock_receipt_item = null;

    #[ORM\ManyToOne]
    private ?PurchaseItem $purchase_item = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __toString(): string
    {
        return $this->description ?? 'Invoice Item';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVendorInvoice(): ?VendorInvoice
    {
        return $this->vendor_invoice;
    }

    public function setVendorInvoice(?VendorInvoice $vendor_invoice): static
    {
        $this->vendor_invoice = $vendor_invoice;
        return $this;
    }

    public function getStockTarget(): ?StockTarget
    {
        return $this->stock_target;
    }

    public function setStockTarget(?StockTarget $stock_target): static
    {
        $this->stock_target = $stock_target;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;
        $this->recalculateLineTotal();
        return $this;
    }

    public function getUnitOfMeasure(): ?string
    {
        return $this->unit_of_measure;
    }

    public function setUnitOfMeasure(?string $unit_of_measure): static
    {
        $this->unit_of_measure = $unit_of_measure;
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unit_price;
    }

    public function setUnitPrice(string $unit_price): static
    {
        $this->unit_price = $unit_price;
        $this->recalculateLineTotal();
        $this->checkPriceVariance();
        return $this;
    }

    public function getLineTotal(): ?string
    {
        return $this->line_total;
    }

    public function setLineTotal(string $line_total): static
    {
        $this->line_total = $line_total;
        return $this;
    }

    public function getExpenseAccount(): ?Account
    {
        return $this->expense_account;
    }

    public function setExpenseAccount(?Account $expense_account): static
    {
        $this->expense_account = $expense_account;
        return $this;
    }

    public function getCostCenter(): ?string
    {
        return $this->cost_center;
    }

    public function setCostCenter(?string $cost_center): static
    {
        $this->cost_center = $cost_center;
        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function getExpectedUnitPrice(): ?string
    {
        return $this->expected_unit_price;
    }

    public function setExpectedUnitPrice(?string $expected_unit_price): static
    {
        $this->expected_unit_price = $expected_unit_price;
        $this->checkPriceVariance();
        return $this;
    }

    public function getPriceVariancePct(): ?string
    {
        return $this->price_variance_pct;
    }

    public function setPriceVariancePct(?string $price_variance_pct): static
    {
        $this->price_variance_pct = $price_variance_pct;
        return $this;
    }

    public function isVarianceFlagged(): ?bool
    {
        return $this->variance_flagged;
    }

    public function setVarianceFlagged(bool $variance_flagged): static
    {
        $this->variance_flagged = $variance_flagged;
        return $this;
    }

    public function getStockReceiptItem(): ?StockReceiptItem
    {
        return $this->stock_receipt_item;
    }

    public function setStockReceiptItem(?StockReceiptItem $stock_receipt_item): static
    {
        $this->stock_receipt_item = $stock_receipt_item;
        return $this;
    }

    public function getPurchaseItem(): ?PurchaseItem
    {
        return $this->purchase_item;
    }

    public function setPurchaseItem(?PurchaseItem $purchase_item): static
    {
        $this->purchase_item = $purchase_item;
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

    public function recalculateLineTotal(): void
    {
        $qty = (float)$this->quantity;
        $price = (float)$this->unit_price;
        $total = $qty * $price;
        
        $this->line_total = number_format($total, 2, '.', '');
        
        // Trigger parent invoice recalculation
        if ($this->vendor_invoice) {
            $this->vendor_invoice->recalculateTotals();
        }
    }

    public function checkPriceVariance(): void
    {
        if (!$this->expected_unit_price || !$this->unit_price) {
            $this->price_variance_pct = null;
            $this->variance_flagged = false;
            return;
        }
        
        $expected = (float)$this->expected_unit_price;
        $actual = (float)$this->unit_price;
        
        if ($expected == 0) {
            $this->price_variance_pct = null;
            $this->variance_flagged = false;
            return;
        }
        
        $variancePct = (($actual - $expected) / $expected) * 100;
        $this->price_variance_pct = number_format($variancePct, 2, '.', '');
        
        // Flag if variance is > 5%
        // # TODO: load AP variance alert threshold from configuration
        $this->variance_flagged = abs($variancePct) > 5.0;
    }

    public function getPriceVarianceAmount(): float
    {
        if (!$this->expected_unit_price || !$this->unit_price) {
            return 0.0;
        }
        
        return (float)$this->unit_price - (float)$this->expected_unit_price;
    }

    public function getLineTotalVariance(): float
    {
        if (!$this->expected_unit_price) {
            return 0.0;
        }
        
        $expectedTotal = (float)$this->quantity * (float)$this->expected_unit_price;
        $actualTotal = (float)$this->line_total;
        
        return $actualTotal - $expectedTotal;
    }
}
