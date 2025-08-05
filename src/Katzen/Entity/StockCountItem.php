<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockCountItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockCountItemRepository::class)]
class StockCountItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockCountItems')]
    private ?StockCount $stock_count = null;

    #[ORM\ManyToOne]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $expected_qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $counted_qty = null;

    #[ORM\ManyToOne]
    private ?Unit $unit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStockCount(): ?StockCount
    {
        return $this->stock_count;
    }

    public function setStockCount(?StockCount $stock_count): static
    {
        $this->stock_count = $stock_count;

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

    public function getExpectedQty(): ?string
    {
        return $this->expected_qty;
    }

    public function setExpectedQty(?string $expected_qty): static
    {
        $this->expected_qty = $expected_qty;

        return $this;
    }

    public function getCountedQty(): ?string
    {
        return $this->counted_qty;
    }

    public function setCountedQty(?string $counted_qty): static
    {
        $this->counted_qty = $counted_qty;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
