<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\RecipeIngredientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeIngredientRepository::class)]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'recipeIngredients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;

    #[ORM\Column(length: 255)]
    private ?string $supply_type = null;

    #[ORM\Column]
    private ?int $supply_id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $quantity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $unit = null;
  
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
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

  // TODO: is this cool?
    private ?object $supplyObject = null;

  // Handles the polymorphic association between items, recipes, etc
    public function getSupply(?EntityManagerInterface $em = null): ?object
    {
        if ($this->supplyObject !== null) {
            return $this->supplyObject;
        }

        if (!$em) {
            throw new \LogicException('EntityManager is required to resolve supply.');
        }

        if ($this->supplyType === 'item') {
          $this->supplyObject = $em->getRepository(Item::class)->find($this->supplyId);
        } elseif ($this->supplyType === 'recipe') {
          $this->supplyObject = $em->getRepository(Recipe::class)->find($this->supplyId);
        } else {
          throw new \UnexpectedValueException("Unknown supply_type: {$this->supplyType}");
        }
        
        return $this->supplyObject;
    }

    public function setSupply(object $supply): void
    {
        if ($supply instanceof Item) {
          $this->supplyType = 'item';
          $this->supplyId = $supply->getId();
        } elseif ($supply instanceof Recipe) {
          $this->supplyType = 'recipe';
          $this->supplyId = $supply->getId();
        } else {
          throw new \InvalidArgumentException('Unsupported supply type.');
        }
        
        $this->supplyObject = $supply;
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
