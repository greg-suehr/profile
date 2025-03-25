<?php

namespace App\Entity;

use App\Repository\ItemUPCRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemUPCRepository::class)]
class ItemUPC
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\Column(length: 255)]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $quantity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $unit = null;

    #[ORM\ManyToOne(inversedBy: 'upc')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ItemVariant $itemVariant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getItemVariant(): ?ItemVariant
    {
        return $this->itemVariant;
    }

    public function setItemVariant(?ItemVariant $itemVariant): static
    {
        $this->itemVariant = $itemVariant;

        return $this;
    }
}
