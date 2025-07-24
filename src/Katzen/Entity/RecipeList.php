<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\RecipeListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeListRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RecipeList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archived_at = null;

    /**
     * @var Collection<int, Recipe>
     */
    #[ORM\ManyToMany(targetEntity: Recipe::class)]
    private Collection $recipes;

    public function __construct()
    {
        $this->recipes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }
   
   #[ORM\PrePersist]
    public function setCreatedAt(): static
    {
        $this->created_at = new \DateTimeImmutable;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAt(): static
    {
        $this->updated_at = new \DateTime;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archived_at;
    }

    public function setArchivedAt(\DateTimeInterface $archived_at): static
    {
        $this->archived_at = $archived_at;

        return $this;
    }

    /**
     * @return Collection<int, Recipe>
     */
    public function getRecipes(): Collection
    {
        return $this->recipes;
    }

    public function addRecipe(Recipe $recipe): static
    {
        if (!$this->recipes->contains($recipe)) {
            $this->recipes->add($recipe);
        }

        return $this;
    }

    public function removeRecipe(Recipe $recipe): static
    {
        $this->recipes->removeElement($recipe);

        return $this;
    }

  # Virtual tag attributes
  private ?string $meal_type = null;
  private ?string $status = null;
  private bool $current = false;
  
  public function getMealType(): ?string { return $this->meal_type; }
  public function setMealType(?string $meal_type): void { $this->meal_type = $meal_type; }
  
  public function getStatus(): ?string { return $this->status; }
  public function setStatus(?string $status): void { $this->status = $status; }
  
  public function isCurrent(): bool { return $this->current; }
  public function setCurrent(bool $current): void { $this->current = $current; }
}
