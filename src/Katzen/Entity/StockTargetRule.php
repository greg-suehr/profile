<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockTargetRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockTargetRuleRepository::class)]
class StockTargetRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'stockTargetRule', cascade: ['persist', 'remove'])]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $min_qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $max_qty = null;

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

    public function getMinQty(): ?string
    {
        return $this->min_qty;
    }

    public function setMinQty(?string $min_qty): static
    {
        $this->min_qty = $min_qty;

        return $this;
    }

    public function getMaxQty(): ?string
    {
        return $this->max_qty;
    }

    public function setMaxQty(?string $max_qty): static
    {
        $this->max_qty = $max_qty;

        return $this;
    }
}
