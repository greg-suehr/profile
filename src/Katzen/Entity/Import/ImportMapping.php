<?php

namespace App\Katzen\Entity\Import;

use App\Katzen\Repository\Import\ImportMappingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportMappingRepository::class)]
#[ORM\Table(name: 'import_mapping')]
#[ORM\Index(name: 'idx_import_mapping_entity_type', columns: ['entity_type'])]
#[ORM\Index(name: 'idx_import_mapping_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class ImportMapping
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\Column(length: 255)]
  private ?string $name = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $description = null;
  
  #[ORM\Column(length: 100)]
  private ?string $entity_type = null;  // order, item, sellable, sellable_variant, vendor, etc.
  
  #[ORM\Column(type: Types::JSON)]
  private array $field_mappings = [];
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $transformation_rules = null;
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $validation_rules = null;
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $default_values = null;
  
  #[ORM\Column]
  private bool $is_active = true;
  
  #[ORM\Column]
  private bool $is_system_template = false;  // true = system-provided, false = usercreated
  
  #[ORM\Column(nullable: true)]
  private ?int $created_by = null;
  
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private ?\DateTimeInterface $created_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updated_at = null;
  
  public function __construct()
  {
  }

  public function __toString(): string
  {
    return $this->name ?? 'Import Mapping #' . $this->id;
 }

  #[ORM\PrePersist]
  public function setCreatedAt(): void
  {
    $this->created_at = new \DateTimeImmutable();
  }

  #[ORM\PrePersist]
  #[ORM\PreUpdate]
  public function setUpdatedAt(): void
  {
    $this->updated_at = new \DateTime();
  }

  public function isSystemTemplate(): bool
  {
    return $this->is_system_template;
  }

  public function isActive(): bool
  {
    return this->is_active;
  }

  public function getId(): ?int { return $this->id; }
  public function getName(): ?string { return $this->name; }
  public function setName(string $name): static { $this->name = $name; return $this; }
  public function getDescription(): ?string { return $this->description; }
  public function setDescription(string $description): static { $this->description = $description; return $this; }
  public function getEntityType(): ?string { return $this->entity_type; }
  public function setEntityType(string $entity_type): static { $this->entity_type = $entity_type; return $this; }
  public function getFieldMappings(): array { return $this->field_mappings; }
  public function setFieldMappings(array $field_mappings): static { $this->field_mappings = $field_mappings; return $this; }
  public function getTransformationRules(): ?array { return $this->transformation_rules; }
  public function setTransformationRules(array $transformation_rules): static { $this->transformation_rules = $transformation_rules; return $this; }
  public function getValidationRules(): ?array { return $this->validation_rules; }
  public function setValidationRules(array $validation_rules): static { $this->validation_rules = $validation_rules; return $this; }
  public function getDefaultValues(): ?array { return $this->default_values; }
  public function setDefaultValues(array $default_values): static { $this->default_values = $default_values; return $this; }
  public function getIsActive(): bool { return $this->is_active; }
  public function setIsActive(bool $is_active): static { $this->is_active = $is_active; return $this; }
  public function getIsSystemTemplate(): bool { return $this->is_system_template; }
  public function setIsSystemTemplate(bool $is_system_template): static { $this->is_system_template = $is_system_template; return $this; }
  public function getCreatedBy(): ?int { return $this->created_by; }
  public function setCreatedBy(int $created_by): static { $this->created_by = $created_by; return $this; }
  public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }
}
