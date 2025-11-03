<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\SellableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellableRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Sellable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = 'simple'; // 'simple', 'modifier', 'variant_parent', 'configurable', 'bundle'

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null; // 'entrée', 'beverage', 'dessert', etc.

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $portionMultiplier = '1.0000';

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sellable $parent = null;

    /**
     * @var Collection<int, Sellable>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    /**
     * @var Collection<int, SellableComponent>
     */
    #[ORM\OneToMany(targetEntity: SellableComponent::class, mappedBy: 'sellable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $components;

    /**
     * @var Collection<int, SellableVariant>
     */
    #[ORM\OneToMany(targetEntity: SellableVariant::class, mappedBy: 'sellable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    /**
     * @var Collection<int, SellableModifierGroup>
     */
    #[ORM\OneToMany(targetEntity: SellableModifierGroup::class, mappedBy: 'sellable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $modifierGroups;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $basePrice = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // 'active', 'inactive', 'discontinued'

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->components = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->modifierGroups = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->name ?? 'Sellable #' . $this->id;
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getPortionMultiplier(): ?string
    {
        return $this->portionMultiplier;
    }

    public function setPortionMultiplier(?string $portionMultiplier): static
    {
        $this->portionMultiplier = $portionMultiplier;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, Sellable>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Sellable $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Sellable $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SellableComponent>
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(SellableComponent $component): static
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setSellable($this);
        }
        return $this;
    }

    public function removeComponent(SellableComponent $component): static
    {
        if ($this->components->removeElement($component)) {
            if ($component->getSellable() === $this) {
                $component->setSellable(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SellableVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(SellableVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setSellable($this);
        }
        return $this;
    }

    public function removeVariant(SellableVariant $variant): static
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getSellable() === $this) {
                $variant->setSellable(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SellableModifierGroup>
     */
    public function getModifierGroups(): Collection
    {
        return $this->modifierGroups;
    }

    public function addModifierGroup(SellableModifierGroup $modifierGroup): static
    {
        if (!$this->modifierGroups->contains($modifierGroup)) {
            $this->modifierGroups->add($modifierGroup);
            $modifierGroup->setSellable($this);
        }
        return $this;
    }

    public function removeModifierGroup(SellableModifierGroup $modifierGroup): static
    {
        if ($this->modifierGroups->removeElement($modifierGroup)) {
            if ($modifierGroup->getSellable() === $this) {
                $modifierGroup->setSellable(null);
            }
        }
        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): static
    {
        $this->basePrice = $basePrice;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}