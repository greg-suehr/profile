<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockReceiptItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockReceiptItemRepository::class)]
class StockReceiptItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, PurchaseItem>
     */
    #[ORM\ManyToMany(targetEntity: PurchaseItem::class, inversedBy: 'stockReceiptItems')]
    private Collection $purchaseItem;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty_received = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty_returned = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lot_number = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiration_date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?StockTransaction $stockTransaction = null;

    #[ORM\ManyToOne]
    private ?StockTarget $stock_target = null;

    /**
     * @var Collection<int, StockLot>
     */
    #[ORM\OneToMany(targetEntity: StockLot::class, mappedBy: 'stockReceiptItem')]
    private Collection $stock_lots;

    #[ORM\ManyToOne(inversedBy: 'stock_receipt_items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockReceipt $stock_receipt = null;

    public function __construct()
    {
        $this->purchaseItem = new ArrayCollection();
        $this->stock_lots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, PurchaseItem>
     */
    public function getPurchaseItem(): Collection
    {
        return $this->purchaseItem;
    }

    public function addPurchaseItem(PurchaseItem $purchaseItem): static
    {
        if (!$this->purchaseItem->contains($purchaseItem)) {
            $this->purchaseItem->add($purchaseItem);
        }

        return $this;
    }

    public function removePurchaseItem(PurchaseItem $purchaseItem): static
    {
        $this->purchaseItem->removeElement($purchaseItem);

        return $this;
    }

    public function getUnitCost(): ?string
    {
        # TODO: not this
        $costSource = $this->purchaseItem[0];
        return $costSource->getUnitPrice();
    }

  public function getLineTotal(): ?string
  {
    return $this->qty_received * $this->getUnitCost();
  }

    public function getQtyReceived(): ?string
    {
        return $this->qty_received;
    }

    public function setQtyReceived(string $qty_received): static
    {
        $this->qty_received = $qty_received;

        return $this;
    }

    public function getQtyReturned(): ?string
    {
        return $this->qty_returned;
    }

    public function setQtyReturned(string $qty_returned): static
    {
        $this->qty_returned = $qty_returned;

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

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expiration_date;
    }

    public function setExpirationDate(?\DateTimeInterface $expiration_date): static
    {
        $this->expiration_date = $expiration_date;

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

    public function getStockTransaction(): ?StockTransaction
    {
        return $this->stockTransaction;
    }

    public function setStocktransaction(?StockTransaction $stockTransaction): static
    {
        $this->stockTransaction = $stockTransaction;

        return $this;
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function getStockLots(): Collection
    {
        return $this->stock_lots;
    }

    public function addStockLot(StockLot $stockLot): static
    {
        if (!$this->stock_lots->contains($stockLot)) {
            $this->stock_lots->add($stockLot);
            $stockLot->setStockReceiptItem($this);
        }

        return $this;
    }

    public function removeStockLot(StockLot $stockLot): static
    {
        if ($this->stock_lots->removeElement($stockLot)) {
            // set the owning side to null (unless already changed)
            if ($stockLot->getStockReceiptItem() === $this) {
                $stockLot->setStockReceiptItem(null);
            }
        }

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

    public function getStockReceipt(): ?StockReceipt
    {
        return $this->stock_receipt;
    }

    public function setStockReceipt(?StockReceipt $stock_receipt): static
    {
        $this->stock_receipt = $stock_receipt;

        return $this;
    }
}
