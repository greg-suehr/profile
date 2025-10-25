<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockLotAllocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLotAllocationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockLotAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty = null;

    #[ORM\Column(length: 50)]
    private ?string $allocation_type = null;

    #[ORM\Column(length: 50)]
    private ?string $direction = null;

    /**
     * @var Collection<int, StockTransaction>
     */
    #[ORM\OneToMany(targetEntity: StockTransaction::class, mappedBy: 'allocation')]
    private Collection $transactions;

    /**
     * @var Collection<int, StockLot>
     */
    #[ORM\OneToMany(targetEntity: StockLot::class, mappedBy: 'allocated_from')]
    private Collection $from_lots;

    /**
     * @var Collection<int, StockLot>
     */
    #[ORM\OneToMany(targetEntity: StockLot::class, mappedBy: 'allocated_to')]
    private Collection $to_lots;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total_cost = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $allocated_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->from_lots = new ArrayCollection();        
        $this->to_lots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAllocationType(): ?string
    {
        return $this->allocation_type;
    }

    public function setAllocationType(string $allocation_type): static
    {
        $this->allocation_type = $allocation_type;

        return $this;
    }

    /**
     * @return Collection<int, StockTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(StockTransaction $stockTransaction): static
    {
        if (!$this->transactions->contains($stockTransaction)) {
            $this->transactions->add($stockTransaction);
            $stockTransaction->setStockLotAllocation($this);
        }

        return $this;
    }

    public function removeTransaction(StockTransaction $stockTransaction): static
    {
        if ($this->transactions->removeElement($stockTransaction)) {
            if ($stockTransaction->getStockLotAllocation() === $this) {
                $stockTransaction->setStockLotAllocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function getFromLots(): Collection
    {
        return $this->from_lots;
    }

    public function addFromLot(StockLot $stockLot): static
    {
        if (!$this->from_lots->contains($stockLot)) {
            $this->from_lots->add($stockLot);
            $stockLot->addAllocatedFrom($this);
        }

        return $this;
    }

    public function removeFromLot(StockLot $stockLot): static
    {
        if ($this->from_lots->removeElement($stockLot)) {
            $from_lots->removeAllocatedFrom($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function getToLots(): Collection
    {
        return $this->to_lots;
    }

    public function addToLot(StockLot $toLot): static
    {
        if (!$this->to_lots->contains($toLot)) {
            $this->to_lots->add($toLot);
            $toLot->setStockLotAllocation($this);
        }

        return $this;
    }

    public function removeToLot(StockLot $toLot): static
    {
        if ($this->to_lots->removeElement($toLot)) {
            if ($toLot->getStockLotAllocation() === $this) {
                $toLot->setStockLotAllocation(null);
            }
        }

        return $this;
    }

    public function getTotalCost(): ?string
    {
        return $this->total_cost;
    }

    public function setTotalCost(string $total_cost): static
    {
        $this->total_cost = $total_cost;

        return $this;
    }

    public function getAllocatedAt(): ?\DateTimeInterface
    {
        return $this->allocated_at;
    }

    public function setAllocatedAt(\DateTimeInterface $allocated_at): static
    {
        $this->allocated_at = $allocated_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): static
    {
        $this->created_at = new \DateTimeImmutable();

        return $this;
    }
}
