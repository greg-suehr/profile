<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockLotTransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLotTransferRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockLotTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transfers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLot $stock_lot = null;

    #[ORM\ManyToOne(inversedBy: 'outgoing_transfers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLocation $from_location = null;

    #[ORM\ManyToOne(inversedBy: 'incoming_transfers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockLocation $to_location = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $qty = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $transfer_date = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $received_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notes = null;

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

    public function getFromLocation(): ?StockLocation
    {
        return $this->from_location;
    }

    public function setFromLocation(?StockLocation $from_location): static
    {
        $this->from_location = $from_location;

        return $this;
    }

    public function getToLocation(): ?StockLocation
    {
        return $this->to_location;
    }

    public function setToLocation(?StockLocation $to_location): static
    {
        $this->to_location = $to_location;

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

    public function getTransferDate(): ?\DateTimeInterface
    {
        return $this->transfer_date;
    }

    public function setTransferDate(\DateTimeInterface $transfer_date): static
    {
        $this->transfer_date = $transfer_date;

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

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->received_at;
    }

    public function setReceivedAt(?\DateTimeInterface $received_at): static
    {
        $this->received_at = $received_at;

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
    #[ORM\PreUpdate]
    public function setUpdatedAt(): static
    {
        $this->updated_at = new \DateTime();

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
