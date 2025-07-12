<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\ItemVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemVariantRepository::class)]
class ItemVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'itemVariants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, ItemUPC>
     */
    #[ORM\OneToMany(targetEntity: ItemUPC::class, mappedBy: 'itemVariant', orphanRemoval: true)]
    private Collection $upc;

    public function __construct()
    {
        $this->upc = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, ItemUPC>
     */
    public function getUpc(): Collection
    {
        return $this->upc;
    }

    public function addUpc(ItemUPC $upc): static
    {
        if (!$this->upc->contains($upc)) {
            $this->upc->add($upc);
            $upc->setItemVariant($this);
        }

        return $this;
    }

    public function removeUpc(ItemUPC $upc): static
    {
        if ($this->upc->removeElement($upc)) {
            // set the owning side to null (unless already changed)
            if ($upc->getItemVariant() === $this) {
                $upc->setItemVariant(null);
            }
        }

        return $this;
    }
}
