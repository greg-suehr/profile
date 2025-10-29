<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\PriceHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceHistoryRepository::class)]
#[ORM\UniqueConstraint(
    name: 'unique_price_point',
    columns: ['vendor_id', 'stock_target_id', 'effective_date', 'source_type', 'source_id']
)]
#[ORM\Index(name: 'idx_price_history_vendor_target', columns: ['vendor_id', 'stock_target_id'])]
#[ORM\Index(name: 'idx_price_history_effective_date', columns: ['effective_date'])]
class PriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vendor $vendor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $unit_price = '0.0000';

    #[ORM\Column(length: 50)]
    private ?string $unit_of_measure = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $effective_date = null;

    #[ORM\Column(length: 50)]
    private ?string $source_type = 'invoice'; // invoice, quote, manual, import

    #[ORM\Column(nullable: true)]
    private ?int $source_id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $quantity_purchased = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $recorded_at = null;

    #[ORM\Column(nullable: true)]
    private ?int $recorded_by = null;

    public function __construct()
    {
        $this->recorded_at = new \DateTime();
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

    public function getStockTarget(): ?StockTarget
    {
        return $this->stock_target;
    }

    public function setStockTarget(?StockTarget $stock_target): static
    {
        $this->stock_target = $stock_target;
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unit_price;
    }

    public function setUnitPrice(string $unit_price): static
    {
        $this->unit_price = $unit_price;
        return $this;
    }

    public function getUnitOfMeasure(): ?string
    {
        return $this->unit_of_measure;
    }

    public function setUnitOfMeasure(string $unit_of_measure): static
    {
        $this->unit_of_measure = $unit_of_measure;
        return $this;
    }

    public function getEffectiveDate(): ?\DateTimeInterface
    {
        return $this->effective_date;
    }

    public function setEffectiveDate(\DateTimeInterface $effective_date): static
    {
        $this->effective_date = $effective_date;
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

    public function getSourceId(): ?int
    {
        return $this->source_id;
    }

    public function setSourceId(?int $source_id): static
    {
        $this->source_id = $source_id;
        return $this;
    }

    public function getQuantityPurchased(): ?string
    {
        return $this->quantity_purchased;
    }

    public function setQuantityPurchased(?string $quantity_purchased): static
    {
        $this->quantity_purchased = $quantity_purchased;
        return $this;
    }

    public function getRecordedAt(): ?\DateTimeInterface
    {
        return $this->recorded_at;
    }

    public function setRecordedAt(\DateTimeInterface $recorded_at): static
    {
        $this->recorded_at = $recorded_at;
        return $this;
    }

    public function getRecordedBy(): ?int
    {
        return $this->recorded_by;
    }

    public function setRecordedBy(?int $recorded_by): static
    {
        $this->recorded_by = $recorded_by;
        return $this;
    }
}
