<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\SellableVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellableVariantRepository::class)]
class SellableVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sellable $sellable = null;

    #[ORM\Column(length: 255)]
    private ?string $variantName = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $priceAdjustment = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $portionMultiplier = '1.0000';

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $sortOrder = 0;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // 'active', 'inactive'

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->variantName ?? 'Variant #' . $this->id;
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSellable(): ?Sellable
    {
        return $this->sellable;
    }

    public function setSellable(?Sellable $sellable): static
    {
        $this->sellable = $sellable;
        return $this;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function setVariantName(string $variantName): static
    {
        $this->variantName = $variantName;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getPriceAdjustment(): ?string
    {
        return $this->priceAdjustment;
    }

    public function setPriceAdjustment(?string $priceAdjustment): static
    {
        $this->priceAdjustment = $priceAdjustment;
        return $this;
    }

    public function getPortionMultiplier(): ?string
    {
        return $this->portionMultiplier;
    }

    public function setPortionMultiplier(?string $portionMultiplier): static
    {
        $this->portionMultiplier = $portionMultiplier;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}