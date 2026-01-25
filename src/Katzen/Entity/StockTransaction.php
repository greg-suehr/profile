<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockTransactionRepository::class)]
class StockTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockTransactions')]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $use_type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $qty = null;

    #[ORM\ManyToOne]
    private ?Unit $unit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $effective_date = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $recorded_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiration_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lot_number = null; 

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $unit_cost = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null; // pending, completed, reversed

    #[ORM\Column(nullable: true)]
    private ?int $batch_id = null;

    #[ORM\ManyToOne]
    private ?LedgerEntry $ledger_entry = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?StockLotAllocation $stockLotAllocation = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUseType(): ?string
    {
        return $this->use_type;
    }

    public function setUseType(?string $use_type): static
    {
        $this->use_type = $use_type;

        return $this;
    }

    public function getQty(): ?string
    {
        return $this->qty;
    }

    public function setQty(?string $qty): static
    {
        $this->qty = $qty;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

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

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expiration_date;
    }

    public function setExpirationDate(?\DateTimeInterface $expiration_date): static
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }

  public function getLotNumber(): ?string
  {
      return $this->lot_number;
  }

  public function setLotNumber(?string $lot_number): static
  {
      $this->lot_number = $lot_number;
      return $this;
  }

  public function getRecordedAt(): ?\DateTimeImmutable
  {
    return $this->recorded_at;
  }
  
  #[ORM\PrePersist]
  public function setRecordedAt(): static
  {
    $this->recorded_at = new \DateTimeImmutable();
    return $this;
  }

    public function getUnitCost(): ?string
    {
        return $this->unit_cost;
    }

    public function setUnitCost(?string $unit_cost): static
    {
        $this->unit_cost = $unit_cost;

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

    public function getBatchId(): ?int
    {
        return $this->batch_id;
    }

    public function setBatchId(?int $batch_id): static
    {
        $this->batch_id = $batch_id;

        return $this;
    }

    public function getLedgerEntry(): ?LedgerEntry
    {
        return $this->ledger_entry;
    }

    public function setLedgerEntry(?LedgerEntry $ledger_entry): static
    {
        $this->ledger_entry = $ledger_entry;

        return $this;
    }

    public function getStockLotAllocation(): ?StockLotAllocation
    {
        return $this->stockLotAllocation;
    }

    public function setStockLotAllocation(?StockLotAllocation $stockLotAllocation): static
    {
        $this->stockLotAllocation = $stockLotAllocation;

        return $this;
    }
}
