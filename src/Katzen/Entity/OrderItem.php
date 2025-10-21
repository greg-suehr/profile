<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order_id = null;

    #[ORM\ManyToOne]
    private ?Recipe $recipe_list_recipe_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $unit_price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $cogs = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fulfilled_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?Order
    {
        return $this->order_id;
    }

    public function setOrderId(?Order $order_id): static
    {
        $this->order_id = $order_id;

        return $this;
    }

    public function getRecipeListRecipeId(): ?Recipe
    {
        return $this->recipe_list_recipe_id;
    }

    public function setRecipeListRecipeId(?Recipe $recipe_list_recipe_id): static
    {
        $this->recipe_list_recipe_id = $recipe_list_recipe_id;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getUnitPrice(): ?string
    {
        return $this->unit_price;
    }

    public function setUnitPrice(?string $unit_price): static
    {
        $this->unit_price = $unit_price;

        return $this;
    }

    public function getCogs(): ?string
    {
        return $this->cogs;
    }

    public function setCogs(string $cogs): static
    {
        $this->cogs = $cogs;

        return $this;
    }

    public function getFulfilledAt(): ?\DateTimeInterface
    {
        return $this->fulfilled_at;
    }

    public function setFulfilledAt(?\DateTimeInterface $fulfilled_at): static
    {
        $this->fulfilled_at = $fulfilled_at;

        return $this;
    }
}
