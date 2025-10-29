<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\RecipeCostSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeCostSnapshotRepository::class)]
class RecipeCostSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $ingredient_cost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $labor_cost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $overhead_cost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $total_cost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $servings = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $cost_per_serving = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $suggested_price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 4, nullable: true)]
    private ?string $target_food_cost_pct = null;

    #[ORM\Column(length: 50)]
    private ?string $calculation_method = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $calculated_at = null;

    #[ORM\Column(nullable: true)]
    private ?array $ingredient_breakdown = null;

    private function getId() {
      return $this->id;
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

    public function getIngredientCost(): ?string
    {
        return $this->ingredient_cost;
    }

    public function setIngredientCost(string $ingredient_cost): static
    {
        $this->ingredient_cost = $ingredient_cost;

        return $this;
    }

    public function getLaborCost(): ?string
    {
        return $this->labor_cost;
    }

    public function setLaborCost(string $labor_cost): static
    {
        $this->labor_cost = $labor_cost;

        return $this;
    }

    public function getOverheadCost(): ?string
    {
        return $this->overhead_cost;
    }

    public function setOverheadCost(string $overhead_cost): static
    {
        $this->overhead_cost = $overhead_cost;

        return $this;
    }

    public function getTotalCost(): ?string
    {
        return $this->total_cost;
    }

    public function setTotalCost(string $total_cost): static
    {
        $this->total_cost = $total_cost;

        return $this;
    }

    public function getServings(): ?string
    {
        return $this->servings;
    }

    public function setServings(string $servings): static
    {
        $this->servings = $servings;

        return $this;
    }

    public function getCostPerServing(): ?string
    {
        return $this->cost_per_serving;
    }

    public function setCostPerServing(string $cost_per_serving): static
    {
        $this->cost_per_serving = $cost_per_serving;

        return $this;
    }

    public function getSuggestedPrice(): ?string
    {
        return $this->suggested_price;
    }

    public function setSuggestedPrice(?string $suggested_price): static
    {
        $this->suggested_price = $suggested_price;

        return $this;
    }

    public function getTargetFoodCostPct(): ?string
    {
        return $this->target_food_cost_pct;
    }

    public function setTargetFoodCostPct(?string $target_food_cost_pct): static
    {
        $this->target_food_cost_pct = $target_food_cost_pct;

        return $this;
    }

    public function getCalculationMethod(): ?string
    {
        return $this->calculation_method;
    }

    public function setCalculationMethod(string $calculation_method): static
    {
        $this->calculation_method = $calculation_method;

        return $this;
    }

    public function getCalculatedAt(): ?\DateTimeInterface
    {
        return $this->calculated_at;
    }

    public function setCalculatedAt(\DateTimeInterface $calculated_at): static
    {
        $this->calculated_at = $calculated_at;

        return $this;
    }

    public function getIngredientBreakdown(): ?array
    {
        return $this->ingredient_breakdown;
    }

    public function setIngredientBreakdown(?array $ingredient_breakdown): static
    {
        $this->ingredient_breakdown = $ingredient_breakdown;

        return $this;
    }
}
