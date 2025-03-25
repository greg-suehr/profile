<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]  
    private ?string $category = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fooddb_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scientific_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subcategory = null;

    /**
     * @var Collection<int, ItemVariant>
     */
    #[ORM\OneToMany(targetEntity: ItemVariant::class, mappedBy: 'item')]
    private Collection $itemVariants;

    public function __construct()
    {
        $this->itemVariants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
      return $this->name;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

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

    public function getFooddbId(): ?string
    {
        return $this->fooddb_id;
    }

    public function setFooddbId(?string $fooddb_id): static
    {
        $this->fooddb_id = $fooddb_id;

        return $this;
    }

    public function getScientificName(): ?string
    {
        return $this->scientific_name;
    }

    public function setScientificName(?string $scientific_name): static
    {
        $this->scientific_name = $scientific_name;

        return $this;
    }

    public function getSubcategory(): ?string
    {
        return $this->subcategory;
    }

    public function setSubcategory(?string $subcategory): static
    {
        $this->subcategory = $subcategory;

        return $this;
    }

    /**
     * @return Collection<int, ItemVariant>
     */
    public function getItemVariants(): Collection
    {
        return $this->itemVariants;
    }

    public function addItemVariant(ItemVariant $itemVariant): static
    {
        if (!$this->itemVariants->contains($itemVariant)) {
            $this->itemVariants->add($itemVariant);
            $itemVariant->setItem($this);
        }

        return $this;
    }

    public function removeItemVariant(ItemVariant $itemVariant): static
    {
        if ($this->itemVariants->removeElement($itemVariant)) {
            // set the owning side to null (unless already changed)
            if ($itemVariant->getItem() === $this) {
                $itemVariant->setItem(null);
            }
        }

        return $this;
    }
}
