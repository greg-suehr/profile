<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\PriceRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PriceRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = 'fixed_price'; // 'fixed_price', 'percentage_discount', 'fixed_discount', 'customer_segment', 'volume_tier', 'promotion', 'time_based'

    #[ORM\Column]
    private ?int $priority = 0;

    #[ORM\Column]
    private ?bool $stackable = false;

    #[ORM\Column]
    private ?bool $exclusive = false;

    #[ORM\Column(type: Types::JSON)]
    private array $conditions = [];
    
    #[ORM\Column(type: Types::JSON)]
    private array $actions = [];

    /**
     * @var Collection<int, Sellable>
     */
    #[ORM\ManyToMany(targetEntity: Sellable::class)]
    #[ORM\JoinTable(name: 'price_rule_sellables')]
    private Collection $applicableSellables;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // 'active', 'inactive', 'expired'

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->applicableSellables = new ArrayCollection();
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
        return $this->name ?? 'Price Rule #' . $this->id;
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

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

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isStackable(): ?bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): static
    {
        $this->stackable = $stackable;
        return $this;
    }

    public function isExclusive(): ?bool
    {
        return $this->exclusive;
    }

    public function setExclusive(bool $exclusive): static
    {
        $this->exclusive = $exclusive;
        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @return Collection<int, Sellable>
     */
    public function getApplicableSellables(): Collection
    {
        return $this->applicableSellables;
    }

    public function addApplicableSellable(Sellable $sellable): static
    {
        if (!$this->applicableSellables->contains($sellable)) {
            $this->applicableSellables->add($sellable);
        }
        return $this;
    }

    public function removeApplicableSellable(Sellable $sellable): static
    {
        $this->applicableSellables->removeElement($sellable);
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidTo(): ?\DateTimeInterface
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeInterface $validTo): static
    {
        $this->validTo = $validTo;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status == 'active';
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
