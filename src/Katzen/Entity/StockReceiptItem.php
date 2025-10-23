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
    private ?StockTransaction $stockTransactions = null;

    public function __construct()
    {
        $this->purchaseItem = new ArrayCollection();
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

    public function getStockTransactions(): ?StockTransaction
    {
        return $this->stockTransactions;
    }

    public function setStockTransactions(?StockTransaction $stockTransactions): static
    {
        $this->stockTransactions = $stockTransactions;

        return $this;
    }
}
