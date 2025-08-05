<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockTargetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockTargetRepository::class)]
class StockTarget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    private ?Item $item = null;

    #[ORM\ManyToOne]
    private ?Recipe $recipe = null;

    #[ORM\ManyToOne]
    private ?Unit $base_unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $current_qty = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $status = null;

    /**
     * @var Collection<int, StockTransaction>
     */
    #[ORM\OneToMany(targetEntity: StockTransaction::class, mappedBy: 'stock_target')]
    private Collection $stockTransactions;

    #[ORM\OneToOne(mappedBy: 'stock_target', cascade: ['persist', 'remove'])]
    private ?StockTargetRule $stockTargetRule = null;

    public function __construct()
    {
        $this->stockTransactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

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

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getBaseUnit(): ?Unit
    {
        return $this->base_unit;
    }

    public function setBaseUnit(?Unit $base_unit): static
    {
        $this->base_unit = $base_unit;

        return $this;
    }

    public function getCurrentQty(): ?string
    {
        return $this->current_qty;
    }

    public function setCurrentQty(?string $current_qty): static
    {
        $this->current_qty = $current_qty;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, StockTransaction>
     */
    public function getStockTransactions(): Collection
    {
        return $this->stockTransactions;
    }

    public function addStockTransaction(StockTransaction $stockTransaction): static
    {
        if (!$this->stockTransactions->contains($stockTransaction)) {
            $this->stockTransactions->add($stockTransaction);
            $stockTransaction->setStockTarget($this);
        }

        return $this;
    }

    public function removeStockTransaction(StockTransaction $stockTransaction): static
    {
        if ($this->stockTransactions->removeElement($stockTransaction)) {
            // set the owning side to null (unless already changed)
            if ($stockTransaction->getStockTarget() === $this) {
                $stockTransaction->setStockTarget(null);
            }
        }

        return $this;
    }

    public function getStockTargetRule(): ?StockTargetRule
    {
        return $this->stockTargetRule;
    }

    public function setStockTargetRule(?StockTargetRule $stockTargetRule): static
    {
        // unset the owning side of the relation if necessary
        if ($stockTargetRule === null && $this->stockTargetRule !== null) {
            $this->stockTargetRule->setStockTarget(null);
        }

        // set the owning side of the relation if necessary
        if ($stockTargetRule !== null && $stockTargetRule->getStockTarget() !== $this) {
            $stockTargetRule->setStockTarget($this);
        }

        $this->stockTargetRule = $stockTargetRule;

        return $this;
    }
}
