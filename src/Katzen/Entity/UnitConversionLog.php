<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\Unit;
use App\Repository\App\Katzen\Entity\UnitConversionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitConversionLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UnitConversionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $from_unit = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $to_unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $original_value = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $converted_value = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10)]
    private ?string $factor_used = null;

    #[ORM\Column(type: Types::JSON)]
    private array $context_data = [];

    #[ORM\Column(type: Types::JSON)]
    private array $conversion_path = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entity_type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entity_id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromUnit(): ?Unit
    {
        return $this->from_unit;
    }

    public function setFromUnit(?Unit $from_unit): static
    {
        $this->from_unit = $from_unit;

        return $this;
    }

    public function getToUnit(): ?Unit
    {
        return $this->to_unit;
    }

    public function setToUnit(?Unit $to_unit): static
    {
        $this->to_unit = $to_unit;

        return $this;
    }

    public function getOriginalValue(): ?string
    {
        return $this->original_value;
    }

    public function setOriginalValue(string $original_value): static
    {
        $this->original_value = $original_value;

        return $this;
    }

    public function getConvertedValue(): ?string
    {
        return $this->converted_value;
    }

    public function setConvertedValue(string $converted_value): static
    {
        $this->converted_value = $converted_value;

        return $this;
    }

    public function getFactorUsed(): ?string
    {
        return $this->factor_used;
    }

    public function setFactorUsed(string $factor_used): static
    {
        $this->factor_used = $factor_used;

        return $this;
    }

    public function getContextData(): array
    {
        return $this->context_data;
    }

    public function setContextData(array $context_data): static
    {
        $this->context_data = $context_data;

        return $this;
    }

    public function getConversionPath(): array
    {
        return $this->conversion_path;
    }

    public function setConversionPath(array $conversion_path): static
    {
        $this->conversion_path = $conversion_path;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entity_type;
    }

    public function setEntityType(?string $entity_type): static
    {
        $this->entity_type = $entity_type;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entity_id;
    }

    public function setEntityId(?string $entity_id): static
    {
        $this->entity_id = $entity_id;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
      if ($this->created_at === null) {
        $this->created_at = new \DateTimeImmutable;
      }
    }
}
