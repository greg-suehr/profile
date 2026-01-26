<?php

namespace App\Katzen\Entity\Import;

use App\Katzen\Repository\Import\ImportErrorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportErrorRepository::class)]
#[ORM\Table(name: 'import_error')]
#[ORM\Index(name: 'idx_import_error_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_import_error_type', columns: ['error_type'])]
#[ORM\Index(name: 'idx_import_error_row', columns: ['batch_id', 'row_number'])]
class ImportError
{
  public const TYPE_VALIDATION = 'validation';
  public const TYPE_TRANSFORMATION = 'transformation';
  public const TYPE_ENTITY_CREATION = 'entity_creation';
  public const TYPE_DUPLICATE = 'duplicate';
  public const TYPE_REFERENCE = 'reference';
  
  public const SEVERITY_WARNING = 'warning';
  public const SEVERITY_ERROR = 'error';
  public const SEVERITY_CRITICAL = 'critical';
  
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\ManyToOne(targetEntity: ImportBatch::class, inversedBy: 'errors')]
  #[ORM\JoinColumn(nullable: false)]
  private ?ImportBatch $batch = null;
  
  #[ORM\Column]
  private int $row_number = 0;
  
  #[ORM\Column(length: 100)]
  private string $error_type = self::TYPE_VALIDATION;
  
  #[ORM\Column(length: 50)]
  private string $severity = self::SEVERITY_ERROR;
  
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $field_name = null;
  
  #[ORM\Column(type: Types::TEXT)]
  private ?string $error_message = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $row_data = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $suggested_fix = null;
  
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private \DateTimeImmutable $created_at;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private \DateTimeInterface $updated_at;
  
  public function __construct()
  {
  }

  public function __toString(): string
  {
    return sprintf(
      'Row %d: %s - %s',
      $this->row_number,
      $this->error_type,
      substr($this->error_message ?? '', 0, 50)
    );
  }
  
  public function getGroupKey(): string
  {
     return $this->error_type . ':' . ($this->field_name ?? 'general');
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

  public function getId(): ?int { return $this->id; }
  public function getBatch(): ?ImportBatch { return $this->batch; }
  public function setBatch(ImportBatch $batch): static { $this->batch = $batch; return $this; }
  public function getRowNumber(): int { return $this->row_number; }
  public function setRowNumber(int $row_number): static { $this->row_number = $row_number; return $this; }
  public function getErrorType(): string { return $this->error_type; }
  public function setErrorType(string $error_type): static { $this->error_type = $error_type; return $this; }
  public function getSeverity(): string { return $this->severity; }
  public function setSeverity(string $severity): static { $this->severity = $severity; return $this; }
  public function getFieldName(): ?string { return $this->field_name; }
  public function setFieldName(string $field_name): static { $this->field_name = $field_name; return $this; }
  public function getErrorMessage(): ?string { return $this->error_message; }
  public function setErrorMessage(string $error_message): static { $this->error_message = $error_message; return $this; }
  public function getRowData(): ?array { return $this->row_data; }
  public function setRowData(array $row_data): static { $this->row_data = $row_data; return $this; }
  public function getSuggestedFix(): ?string { return $this->suggested_fix; }
  public function setSuggestedFix(string $suggested_fix): static { $this->suggested_fix = $suggested_fix; return $this; }
  public function getCreatedAt(): \DateTimeImmutable { return $this->created_at; }
  public function getUpdatedAt(): \DateTimeInterface { return $this->updated_at; }  
}
