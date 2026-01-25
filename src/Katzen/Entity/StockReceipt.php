<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\KatzenUser;
use App\Katzen\Repository\StockReceiptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockReceiptRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $receipt_number = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $received_at = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?KatzenUser $received_by = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending'; // pending, received

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'stock_receipts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLocation $location = null;

    /**
     * @var Collection<int, StockReceiptItem>
     */
  #[ORM\OneToMany(targetEntity: StockReceiptItem::class, mappedBy: 'stock_receipt', cascade: ['persist'])]
    private Collection $stock_receipt_items;

    public function __construct()
    {
        $this->stock_receipt_items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReceiptNumber(): ?string
    {
        return $this->receipt_number;
    }

    public function setReceiptNumber(string $receipt_number): static
    {
        $this->receipt_number = $receipt_number;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->received_at;
    }

    public function setReceivedAt(\DateTimeInterface $received_at): static
    {
        $this->received_at = $received_at;

        return $this;
    }

    public function getReceivedBy(): ?KatzenUser
    {
        return $this->received_by;
    }

    public function setReceivedBy(?KatzenUser $received_by): static
    {
        $this->received_by = $received_by;

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
        $this->created_at = new \DateTimeImmutable;

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
        $this->updated_at = new \DateTime;

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

    public function getLocation(): ?StockLocation
    {
        return $this->location;
    }

    public function setLocation(?StockLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    /**
     * @return Collection<int, StockReceiptItem>
     */
    public function getStockReceiptItems(): Collection
    {
        return $this->stock_receipt_items;
    }

    public function addStockReceiptItem(StockReceiptItem $stockReceiptItem): static
    {
        if (!$this->stock_receipt_items->contains($stockReceiptItem)) {
            $this->stock_receipt_items->add($stockReceiptItem);
            $stockReceiptItem->setStockReceipt($this);
        }

        return $this;
    }

    public function removeStockReceiptItem(StockReceiptItem $stockReceiptItem): static
    {
        if ($this->stock_receipt_items->removeElement($stockReceiptItem)) {
            // set the owning side to null (unless already changed)
            if ($stockReceiptItem->getStockReceipt() === $this) {
                $stockReceiptItem->setStockReceipt(null);
            }
        }

        return $this;
    }

  public function getTotalAmount(): ?string
  {
    $total_amount = 0.00;
    foreach ($this->stock_receipt_items as $item) {
      $total_amount += $item->getLineTotal();
    }
    return $total_amount;
  }
}
