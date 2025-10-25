<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockLotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLotRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockLot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockLots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lot_number = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $production_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiration_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $received_date = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $initial_qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $current_qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $reserved_qty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unit_cost = null;

    #[ORM\ManyToOne(inversedBy: 'stockLots')]
    private ?Vendor $vendor = null;

    #[ORM\ManyToOne(inversedBy: 'stockLots')]
    private ?Purchase $purchase = null;

    #[ORM\ManyToOne(inversedBy: 'stockLots')]
    private ?StockReceiptItem $stock_receipt_item = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'from_lots')]
    private ?StockLotAllocation $allocated_from = null;

    #[ORM\ManyToOne(inversedBy: 'to_lots')]
    private ?StockLotAllocation $allocated_to = null;

    /**
     * @var Collection<int, StockLotLocationBalance>
     */
    #[ORM\OneToMany(targetEntity: StockLotLocationBalance::class, mappedBy: 'stock_lot', orphanRemoval: true)]
    private Collection $locationBalances;

    /**
     * @var Collection<int, StockLotTransfer>
     */
    #[ORM\OneToMany(targetEntity: StockLotTransfer::class, mappedBy: 'stock_lot', orphanRemoval: true)]
    private Collection $transfers;

    #[ORM\ManyToOne(inversedBy: 'stock_lots')]
    private ?StockReceiptItem $stockReceiptItem = null;

    public function __construct()
    {
        $this->locationBalances = new ArrayCollection();
        $this->transfers = new ArrayCollection();
    }

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

    public function getLotNumber(): ?string
    {
        return $this->lot_number;
    }

    public function setLotNumber(?string $lot_number): static
    {
        $this->lot_number = $lot_number;

        return $this;
    }

    public function getProductionDate(): ?\DateTimeInterface
    {
        return $this->production_date;
    }

    public function setProductionDate(?\DateTimeInterface $production_date): static
    {
        $this->production_date = $production_date;

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

    public function getReceivedDate(): ?\DateTimeInterface
    {
        return $this->received_date;
    }

    public function setReceivedDate(\DateTimeInterface $received_date): static
    {
        $this->received_date = $received_date;

        return $this;
    }

    public function getInitialQty(): ?string
    {
        return $this->initial_qty;
    }

    public function setInitialQty(string $initial_qty): static
    {
        $this->initial_qty = $initial_qty;

        return $this;
    }

    public function getCurrentQty(): ?string
    {
        return $this->current_qty;
    }

    public function setCurrentQty(string $current_qty): static
    {
        $this->current_qty = $current_qty;

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

    public function getUnitCost(): ?string
    {
        return $this->unit_cost;
    }

    public function setUnitCost(string $unit_cost): static
    {
        $this->unit_cost = $unit_cost;

        return $this;
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

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): static
    {
        $this->purchase = $purchase;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    #[ORM\PrePersist]
    #[ORM\PostPersist]
    public function setUpdatedAt(): static
    {
        $this->updated_at = new \DateTime();

        return $this;
    }

    public function getAllocatedFrom(): ?StockLotAllocation
    {
        return $this->allocated_from;
    }

    public function setAllocatedFrom(?StockLotAllocation $stockLotAllocation): static
    {
        $this->allocated_from = $stockLotAllocation;

        return $this;
    }
  
    public function getAllocatedTo(): ?StockLotAllocation
    {
        return $this->allocated_to;
    }

    public function setAllocatedTo(?StockLotAllocation $stockLotAllocation): static
    {
        $this->allocated_to = $stockLotAllocation;

        return $this;
    }

    /**
     * @return Collection<int, StockLotLocationBalance>
     */
    public function getLocationBalances(): Collection
    {
        return $this->locationBalances;
    }

    public function addLocationBalance(StockLotLocationBalance $locationBalance): static
    {
        if (!$this->locationBalances->contains($locationBalance)) {
            $this->locationBalances->add($locationBalance);
            $locationBalance->setStockLot($this);
        }

        return $this;
    }

    public function removeLocationBalance(StockLotLocationBalance $locationBalance): static
    {
        if ($this->locationBalances->removeElement($locationBalance)) {
            // set the owning side to null (unless already changed)
            if ($locationBalance->getStockLot() === $this) {
                $locationBalance->setStockLot(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLotTransfer>
     */
    public function getTransfers(): Collection
    {
        return $this->transfers;
    }

    public function addTransfer(StockLotTransfer $transfer): static
    {
        if (!$this->transfers->contains($transfer)) {
            $this->transfers->add($transfer);
            $transfer->setStockLot($this);
        }

        return $this;
    }

    public function removeTransfer(StockLotTransfer $transfer): static
    {
        if ($this->transfers->removeElement($transfer)) {
            // set the owning side to null (unless already changed)
            if ($transfer->getStockLot() === $this) {
                $transfer->setStockLot(null);
            }
        }

        return $this;
    }
}
