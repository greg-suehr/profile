<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockLotLocationBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLotLocationBalanceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockLotLocationBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'locationBalances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLot $stock_lot = null;

    #[ORM\ManyToOne(inversedBy: 'locationBalances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLocation $location = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $reserved_qty = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStockLot(): ?StockLot
    {
        return $this->stock_lot;
    }

    public function setStockLot(?StockLot $stock_lot): static
    {
        $this->stock_lot = $stock_lot;

        return $this;
    }

    public function getLocation(): ?StockLocation
    {
        return $this->location;
    }

    public function setLocation(?StockLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getQty(): ?string
    {
        return $this->qty;
    }

    public function setQty(string $qty): static
    {
        $this->qty = $qty;

        return $this;
    }

    public function getReservedQty(): ?string
    {
        return $this->reserved_qty;
    }

    public function setReservedQty(string $reserved_qty): static
    {
        $this->reserved_qty = $reserved_qty;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAt(): static
    {
        $this->updated_at = new \DateTime();

        return $this;
    }
}
