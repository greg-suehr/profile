<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: KatzenUser::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?KatzenUser $author = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column]
    private ?int $serving_min_qty = null;

    #[ORM\Column(nullable: true)]
    private ?int $serving_max_qty = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false)]  
    private ?Unit $serving_unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $prep_time = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $cook_time = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $wait_time = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column]
    private ?int $version = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?bool $is_public = false;

    /**
     * @var Collection<int, RecipeIngredient>
     */
    #[ORM\OneToMany(targetEntity: RecipeIngredient::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $recipeIngredients;

    /**
     * @var Collection<int, RecipeInstruction>
     */
    #[ORM\OneToMany(targetEntity: RecipeInstruction::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $recipeInstructions;

    public function __construct()
    {
        $this->recipeIngredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?KatzenUser
    {
        return $this->author;
    }

    public function setAuthor(?KatzenUser $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getServingMinQty(): ?int
    {
        return $this->serving_min_qty;
    }

    public function setServingMinQty(int $serving_min_qty): static
    {
        $this->serving_min_qty = $serving_min_qty;

        return $this;
    }

    public function getServingMaxQty(): ?int
    {
        return $this->serving_max_qty;
    }

    public function setServingMaxQty(?int $serving_max_qty): static
    {
        $this->serving_max_qty = $serving_max_qty;

        return $this;
    }

    public function getServingUnit(): ?Unit
    {
        return $this->serving_unit;
    }

    public function setServingUnit(Unit $serving_unit): static
    {
      $this->serving_unit = $serving_unit;
      return $this;
    }

    public function getPrepTime(): ?string
    {
        return $this->prep_time;
    }

    public function setPrepTime(?string $prep_time): static
    {
        $this->prep_time = $prep_time;

        return $this;
    }

    public function getCookTime(): ?string
    {
        return $this->cook_time;
    }

    public function setCookTime(string $cook_time): static
    {
        $this->cook_time = $cook_time;

        return $this;
    }

    public function getWaitTime(): ?string
    {
        return $this->wait_time;
    }

    public function setWaitTime(string $wait_time): static
    {
        $this->wait_time = $wait_time;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->is_public;
    }

    public function setIsPublic(bool $is_public): static
    {
        $this->is_public = $is_public;

        return $this;
    }

    /**
     * @return Collection<int, RecipeIngredient>
     */
    public function getRecipeIngredients(): Collection
    {
        return $this->recipeIngredients;
    }

    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients->add($recipeIngredient);
            $recipeIngredient->setRecipe($this);
        }

        return $this;
    }

    public function removeRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if ($this->recipeIngredients->removeElement($recipeIngredient)) {
            // set the owning side to null (unless already changed)
            if ($recipeIngredient->getRecipe() === $this) {
                $recipeIngredient->setRecipe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RecipeInstruction>
     */
    public function getRecipeInstructions(): Collection
    {
        return $this->recipeInstructions;
    }

    public function addRecipeInstruction(RecipeInstruction $recipeInstruction): static
    {
        if (!$this->recipeInstructions->contains($recipeInstruction)) {
            $this->recipeInstructions->add($recipeInstruction);
            $recipeInstruction->setRecipe($this);
        }

        return $this;
    }

    public function removeRecipeInstruction(RecipeInstruction $recipeInstruction): static
    {
        if ($this->recipeInstructions->removeElement($recipeInstruction)) {
            // set the owning side to null (unless already changed)
            if ($recipeInstruction->getRecipe() === $this) {
                $recipeInstruction->setRecipe(null);
            }
        }

        return $this;
    }  
}
