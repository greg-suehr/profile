<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\SellableComponentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellableComponentRepository::class)]
class SellableComponent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'components')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sellable $sellable = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockTarget $target = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $quantityMultiplier = '1.0000';

    #[ORM\Column(length: 50)]
    private ?string $purpose = 'primary'; // 'primary', 'component', 'garnish', 'packaging'

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $sortOrder = 0;

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

    public function getTarget(): ?StockTarget
    {
        return $this->target;
    }

    public function setTarget(?StockTarget $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function getQuantityMultiplier(): ?string
    {
        return $this->quantityMultiplier;
    }

    public function setQuantityMultiplier(string $quantityMultiplier): static
    {
        $this->quantityMultiplier = $quantityMultiplier;
        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): static
    {
        $this->purpose = $purpose;
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
}