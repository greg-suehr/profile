<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\InvoiceLineItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceLineItemRepository::class)]
class InvoiceLineItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lineItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantity = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unit_price = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $line_total = '0.00';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Recipe $recipe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Item $item = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;
        return $this;
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

    public function calculateLineTotal(): void
    {
        $qty = (float)$this->quantity;
        $price = (float)$this->unit_price;
        $this->line_total = (string)round($qty * $price, 2);
    }
}
