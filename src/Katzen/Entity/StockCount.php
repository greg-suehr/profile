<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockCountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockCountRepository::class)]
class StockCount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notes = null;

    /**
     * @var Collection<int, StockCountItem>
     */
    #[ORM\OneToMany(targetEntity: StockCountItem::class, mappedBy: 'stock_count')]
    private Collection $stockCountItems;

    public function __construct()
    {
        $this->stockCountItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(?\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, StockCountItem>
     */
    public function getStockCountItems(): Collection
    {
        return $this->stockCountItems;
    }

    public function addStockCountItem(StockCountItem $stockCountItem): static
    {
        if (!$this->stockCountItems->contains($stockCountItem)) {
            $this->stockCountItems->add($stockCountItem);
            $stockCountItem->setStockCount($this);
        }

        return $this;
    }

    public function removeStockCountItem(StockCountItem $stockCountItem): static
    {
        if ($this->stockCountItems->removeElement($stockCountItem)) {
            // set the owning side to null (unless already changed)
            if ($stockCountItem->getStockCount() === $this) {
                $stockCountItem->setStockCount(null);
            }
        }

        return $this;
    }
}
