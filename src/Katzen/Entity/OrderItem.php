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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sellable $sellable = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?SellableVariant $sellableVariant = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modifiers = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $quantity = '1.00';
      
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;
      
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $unit_price = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $cogs = '0.00';

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

    public function getSellable(): ?Sellable
    {
        return $this->sellable;
    }

    public function setSellable(?Sellable $sellable): static
    {
        $this->sellable = $sellable;
        return $this;
    }

    public function getSellableVariant(): ?SellableVariant
    {
        return $this->sellableVariant;
    }

    public function setSellableVariant(?SellableVariant $sellableVariant): static
    {
        $this->sellableVariant = $sellableVariant;
        return $this;
    }

    public function getModifiers(): ?array
    {
        return $this->modifiers;
    }

    public function setModifiers(?array $modifiers): static
    {
        $this->modifiers = $modifiers;
        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): static
    {
      if ((float)$quantity <= 0.00) {
        throw new \InvalidArgumentException('Quantity must be greater than 0.');
      }
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
  
  public function getItemSubtotal(): float
  {
    return $this->quantity * $this->unit_price;
  }

  /**
   * Calculate line total
   */
  public function getLineTotal(): float
  {
    return (float)$this->unit_price * $this->quantity;
  }

  /**
   * Calculate gross profit for this line
   */
  public function getGrossProfit(): float
  {
    $revenue = $this->getLineTotal();
    $cost = (float)$this->cogs * $this->quantity;
    return $revenue - $cost;
  }

  /**
   * Calculate gross margin percentage
   */
  public function getGrossMarginPercent(): float
  {
    $total = $this->getLineTotal();
    if ($total <= 0) {
      return 0.0;
    }
    return ($this->getGrossProfit() / $total) * 100;
  }

  public function __toString(): string
  {
    $recipeName = $this->recipe_list_recipe_id?->getName() ?? 'Unknown Item';
    return sprintf('%s (x%d)', $recipeName, $this->quantity);
  }

  public function isFulfilled(): bool
  {
    return $this->fulfilled_at !== null;
  }

  /**
   * Mark this item as fulfilled
   */
  public function fulfill(): void
  {
    $this->fulfilled_at = new \DateTime();

    # TODO: investigate how Doctrine handles many-line order fulfillments
    # when calling $oi->fulfill in loop, then design bulk fulfillment workflows
    if ($this->order_id) {
      $this->order_id->updateFulfillmentStatus();
    }
  }

  /**
   * Unfulfill this item, which makes it available again to fulfillmetn workflows.
   */
  public function unfulfill(): void
  {
    $this->fulfilled_at = null;

    if ($this->order_id) {
      $this->order_id->updateFulfillmentStatus();
    }
  }
}
