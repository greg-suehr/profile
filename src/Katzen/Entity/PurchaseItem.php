<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\PurchaseItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseItemRepository::class)]
class PurchaseItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

    #[ORM\ManyToOne]
    private ?StockTarget $stockTarget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty_ordered = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty_received = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unit_price = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $line_total = '0.00';

    /**
     * @var Collection<int, StockReceiptItem>
     */
    #[ORM\ManyToMany(targetEntity: StockReceiptItem::class, mappedBy: 'purchaseItem')]
    private Collection $stockReceiptItems;

    public function __construct()
    {
        $this->stockReceiptItems = new ArrayCollection();
    }

    public function __toString(): string
    {
      return (string)$this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStockTarget(): ?StockTarget
    {
        return $this->stockTarget;
    }

    public function setStockTarget(?StockTarget $stockTarget): static
    {
        $this->stockTarget = $stockTarget;

        return $this;
    }

    public function getQtyOrdered(): ?string
    {
        return $this->qty_ordered;
    }

    public function setQtyOrdered(string $qty_ordered): static
    {
        $this->qty_ordered = $qty_ordered;

        return $this;
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

    public function getUnitPrice(): ?string
    {
        return $this->unit_price;
    }

    public function setUnitPrice(string $unit_price): static
    {
        $this->unit_price = $unit_price;

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

    /**
     * @return Collection<int, StockReceiptItem>
     */
    public function getStockReceiptItems(): Collection
    {
        return $this->stockReceiptItems;
    }

    public function addStockReceiptItem(StockReceiptItem $stockReceiptItem): static
    {
        if (!$this->stockReceiptItems->contains($stockReceiptItem)) {
            $this->stockReceiptItems->add($stockReceiptItem);
            $stockReceiptItem->addPurchaseItem($this);
        }

        return $this;
    }

    public function removeStockReceiptItem(StockReceiptItem $stockReceiptItem): static
    {
        if ($this->stockReceiptItems->removeElement($stockReceiptItem)) {
            $stockReceiptItem->removePurchaseItem($this);
        }

        return $this;
    }
}
