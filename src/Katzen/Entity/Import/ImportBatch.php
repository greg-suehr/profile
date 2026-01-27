<?php

namespace App\Katzen\Entity\Import;

use App\Katzen\Repository\Import\ImportBatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportBatchRepository::class)]
#[ORM\Table(name: 'import_batch')]
#[ORM\Index(name: 'idx_import_batch_status', columns: ['status'])]
#[ORM\Index(name: 'idx_import_batch_created_by', columns: ['created_by'])]
#[ORM\Index(name: 'idx_import_batch_started_at', columns: ['started_at'])]
#[ORM\HasLifecycleCallbacks]
class ImportBatch
{
  public const STATUS_PENDING = 'pending';
  public const STATUS_PROCESSING = 'processing';
  public const STATUS_COMPLETED = 'completed';
  public const STATUS_FAILED = 'failed';
  public const STATUS_ROLLED_BACK = 'rolled_back';
  
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\Column(length: 255)]
  private ?string $name = null;
  
  #[ORM\Column(length: 50)]
  private string $status = self::STATUS_PENDING;
  
  #[ORM\ManyToOne(cascade: ['persist'])]
  #[ORM\JoinColumn(nullable: false)]
  private ?ImportMapping $mapping = null;
  
  #[ORM\Column]
  private int $total_rows = 0;
  
  #[ORM\Column]
  private int $processed_rows = 0;
  
  #[ORM\Column]
  private int $successful_rows = 0;
  
  #[ORM\Column]
  private int $failed_rows = 0;
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $error_summary = null;
  
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $entity_counts = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $metadata = null;
  
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  private ?\DateTimeImmutable $started_at = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private ?\DateTimeImmutable $created_at = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updated_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $completed_at = null;
  
  #[ORM\Column(nullable: true)]
  private ?int $created_by = null;
  
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $source_file = null;
  
  #[ORM\Column(length: 500, nullable: true)]
  private ?string $source_file_path = null;
  
  /**
   * @var Collection<int, ImportError>
   */
  #[ORM\OneToMany(
    targetEntity: ImportError::class, 
    mappedBy: 'batch', 
    cascade: ['persist', 'remove'],
    orphanRemoval: true
  )]
  private Collection $errors;
  
  public function __construct()
  {
    $this->errors = new ArrayCollection();
  }

  public function __toString(): string
  {
    return $this->name ?? ($this->id ? 'Import Batch #' . $this->id : 'New Import Batch');
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
  
  public function getProgressPercent(): float
  {
    if ($this->total_rows === 0) {
      return 0.0;
    }
    return round(($this->processed_rows / $this->total_rows) * 100, 1);
  }

  public function isPending(): bool
  {
    return $this->status === self::STATUS_PENDING;
  }

  public function isProcessing(): bool
  {
    return $this->status === self::STATUS_PROCESSING;
  }

  public function isCompleted(): bool
  {
    return $this->status === self::STATUS_COMPLETED;
  }

  public function isFailed(): bool
  {
    return $this->status === self::STATUS_FAILED;
  }

  public function canRollback(): bool
  {
    return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]);
  }

  public function markAsProcessing(): void
  {
    $this->status = self::STATUS_PROCESSING;
  }

  public function markAsCompleted(): void
  {
    $this->status = self::STATUS_COMPLETED;
    $this->completed_at = new \DateTime();
  }

  public function markAsFailed(): void
  {
    $this->status = self::STATUS_FAILED;
    $this->completed_at = new \DateTime();
  }

  public function incrementProcessed(): void
  {
    $this->processed_rows++;
  }

  public function incrementSuccessful(): void
  {
    $this->successful_rows++;
  }

  public function incrementFailed(): void
  {
    $this->failed_rows++;
  }

  public function getErrors(): Collection { return $this->errors; }
  
  public function addError(ImportError $error): static 
  {
    if (!$this->errors->contains($error)) {
      $this->errors->add($error);
        $error->setBatch($this);
    }
    return $this;
  }

  public function removeError(ImportError $error): static 
  {
    if ($this->errors->removeElement($error)) {
        if ($error->getBatch() === $this) {
            $error->setBatch(null);
        }
    }
    return $this;
  }

  public function getId(): ?int { return $this->id; }
  public function getName(): ?string { return $this->name; }
  public function setName(string $name): static { $this->name = $name; return $this; }
  public function getStatus(): string { return $this->status; }
  public function setStatus(string $status): static { $this->status = $status; return $this; }
  public function getMapping(): ?ImportMapping { return $this->mapping; }
  public function setMapping(ImportMapping $mapping): static { $this->mapping = $mapping; return $this; }
  public function getTotalRows(): int { return $this->total_rows; }
  public function setTotalRows(int $total_rows): static { $this->total_rows = $total_rows; return $this; }
  public function getProcessedRows(): int { return $this->processed_rows; }
  public function setProcessedRows(int $processed_rows): static { $this->processed_rows = $processed_rows; return $this; }
  public function getSuccessfulRows(): int { return $this->successful_rows; }
  public function setSuccessfulRows(int $successful_rows): static { $this->successful_rows = $successful_rows; return $this; }
  public function getFailedRows(): int { return $this->failed_rows; }
  public function setFailedRows(int $failed_rows): static { $this->failed_rows = $failed_rows; return $this; }
  public function getErrorSummary(): ?array { return $this->error_summary; }
  public function setErrorSummary(array $error_summary): static { $this->error_summary = $error_summary; return $this; }
  public function getEntityCounts(): ?array { return $this->entity_counts; }
  public function setEntityCounts(array $entity_counts): static { $this->entity_counts = $entity_counts; return $this; }
  public function getMetadata(): ?array { return $this->metadata; }
  public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
  public function getStartedAt(): ?\DateTimeInterface{ return $this->started_at; }
  public function setStartedAt(\DateTimeInterface $started_at): static { $this->started_at = $started_at; return $this; }
  public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }
  public function getCompletedAt(): ?\DateTime { return $this->completed_at; }
  public function setCompletedAt(\DateTimeInterface $completed_at): static { $this->completed_at = $completed_at; return $this; }
  public function getCreatedBy(): ?int { return $this->created_by; }
  public function setCreatedBy(int $created_by): static { $this->created_by = $created_by; return $this; }
  public function getSourceFile(): ?string { return $this->source_file; }
  public function setSourceFile(string $source_file): static { $this->source_file = $source_file; return $this; }
  public function getSourceFilePath(): ?string { return $this->source_file_path; }
  public function setSourceFilePath(string $source_file_path): static { $this->source_file_path = $source_file_path; return $this; }
}
