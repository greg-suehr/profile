<?php

namespace App\Entity;

use App\Repository\UnitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitRepository::class)]
class Unit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    private ?string $abbreviation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column]
    private ?int $base_unit_id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4)]
    private ?string $conversion_factor = null;

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

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): static
    {
        $this->abbreviation = $abbreviation;

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

    public function getBaseUnitId(): ?int
    {
        return $this->base_unit_id;
    }

    public function setBaseUnitId(int $base_unit_id): static
    {
        $this->base_unit_id = $base_unit_id;

        return $this;
    }

    public function getConversionFactor(): ?string
    {
        return $this->conversion_factor;
    }

    public function setConversionFactor(string $conversion_factor): static
    {
        $this->conversion_factor = $conversion_factor;

        return $this;
    }
}
