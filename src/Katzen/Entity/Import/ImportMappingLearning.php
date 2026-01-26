<?php

namespace App\Katzen\Entity\Import;

use App\Katzen\Repository\Import\ImportMappingLearningRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportMappingLearningRepository::class)]
#[ORM\Table(name: 'import_mapping_learning')]
#[ORM\UniqueConstraint(
    name: 'unique_column_field_entity', 
    columns: ['column_name', 'target_field', 'entity_type']
)]
#[ORM\Index(name: 'idx_import_mapping_learning_entity_type', columns: ['entity_type'])]
#[ORM\Index(name: 'idx_import_mapping_learning_fingerprint', columns: ['header_fingerprint'])]
#[ORM\HasLifecycleCallbacks]
class ImportMappingLearning
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255)]
  private ?string $column_name = null;
  
  #[ORM\Column(length: 100)]
  private ?string $target_field = null;
  
  #[ORM\Column(length: 100)]
  private ?string $entity_type = null;  // order, item, sellable, sellable_variant, etc.
  
  #[ORM\Column(length: 64, nullable: true)]
  private ?string $header_fingerprint = null;  // MD5 of sorted headers for full-file matching
  
  #[ORM\Column]
  private int $success_count = 0;
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $failed_suggestions = null;
  
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private ?\DateTimeImmutable $created_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updated_at = null;
  
  public function __construct()
  {
  }

  public function __toString(): string
  {
    return sprintf(
      '%s : %s (%s) [%dx]',
      $this->column_name,
      $this->target_field,
      $this->entity_type,
      $this->success_count
    );
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

  public function incrementSuccessCount(): void
  {
    $this->success_count++;
    $this->updated_at = new \DateTime();
  }

  public function recordFailedSuggestion(string $suggestedField): void
  {
    $failed = $this->failed_suggestions ?? [];
    $failed[$suggestedField] = ($failed[$suggestedField] ?? 0) + 1;
    $this->failed_suggestions = $failed;
    $this->updated_at = new \DateTime();
  }
  
  public function getId(): ?int { return $this->id; }
  public function getColumnName(): ?string { return $this->column_name; }
  public function setColumnName(string $column_name): static { $this->column_name = $column_name; return $this; }
  public function getTargetField(): ?string { return $this->target_field; }
  public function setTargetField(string $target_field): static { $this->target_field = $target_field; return $this; }
  public function getEntityType(): ?string { return $this->entity_type; }
  public function setEntityType(string $entity_type): static { $this->entity_type = $entity_type; return $this; }
  public function getHeaderFingerprint(): ?string { return $this->header_fingerprint; }
  public function setHeaderFingerprint(string $header_fingerprint): static { $this->header_fingerprint = $header_fingerprint; return $this; }
  public function getSuccessCount(): int { return $this->success_count; }
  public function setSuccessCount(int $success_count): static { $this->success_count = $success_count; return $this; }
  public function getFailedSuggestions(): ?array { return $this->failed_suggestions; }
  public function setFailedSuggestions(array $failed_suggestions): static { $this->failed_suggestions = $failed_suggestions; return $this; }
  public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }
}
