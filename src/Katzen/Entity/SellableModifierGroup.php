<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\SellableModifierGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellableModifierGroupRepository::class)]
class SellableModifierGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'modifierGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sellable $sellable = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $required = false;

    #[ORM\Column]
    private ?int $minSelections = 0;

    #[ORM\Column(nullable: true)]
    private ?int $maxSelections = null;

    #[ORM\Column]
    private ?int $sortOrder = 0;

    /**
     * @var Collection<int, Sellable>
     */
    #[ORM\ManyToMany(targetEntity: Sellable::class)]
    #[ORM\JoinTable(name: 'sellable_modifier_group_modifiers')]
    private Collection $modifiers;

    public function __construct()
    {
        $this->modifiers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? 'Modifier Group #' . $this->id;
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    public function getMinSelections(): ?int
    {
        return $this->minSelections;
    }

    public function setMinSelections(int $minSelections): static
    {
        $this->minSelections = $minSelections;
        return $this;
    }

    public function getMaxSelections(): ?int
    {
        return $this->maxSelections;
    }

    public function setMaxSelections(?int $maxSelections): static
    {
        $this->maxSelections = $maxSelections;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @return Collection<int, Sellable>
     */
    public function getModifiers(): Collection
    {
        return $this->modifiers;
    }

    public function addModifier(Sellable $modifier): static
    {
        if (!$this->modifiers->contains($modifier)) {
            $this->modifiers->add($modifier);
        }
        return $this;
    }

    public function removeModifier(Sellable $modifier): static
    {
        $this->modifiers->removeElement($modifier);
        return $this;
    }
}