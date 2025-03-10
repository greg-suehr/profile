<?php

namespace App\Entity;

use App\Repository\RecipeIngredientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeIngredientRepository::class)]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $ingredient_id = null;

    #[ORM\ManyToOne(inversedBy: 'recipeIngredients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe_id = null;

    #[ORM\Column(length: 255)]
    private ?string $supply_type = null;

    #[ORM\Column]
    private ?int $supply_id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $quantity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $unit_id = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIngredientId(): ?int
    {
        return $this->ingredient_id;
    }

    public function setIngredientId(int $ingredient_id): static
    {
        $this->ingredient_id = $ingredient_id;

        return $this;
    }

    public function getRecipeId(): ?Recipe
    {
        return $this->recipe_id;
    }

    public function setRecipeId(?Recipe $recipe_id): static
    {
        $this->recipe_id = $recipe_id;

        return $this;
    }

    public function getSupplyType(): ?string
    {
        return $this->supply_type;
    }

    public function setSupplyType(string $supply_type): static
    {
        $this->supply_type = $supply_type;

        return $this;
    }

    public function getSupplyId(): ?int
    {
        return $this->supply_id;
    }

    public function setSupplyId(int $supply_id): static
    {
        $this->supply_id = $supply_id;

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

    public function getUnitId(): ?Unit
    {
        return $this->unit_id;
    }

    public function setUnitId(?Unit $unit_id): static
    {
        $this->unit_id = $unit_id;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
