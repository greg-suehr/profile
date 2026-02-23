<?php

namespace App\Shared\Entity;

use App\Shared\Repository\ProfileDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileDocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProfileDocument
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\Column(length: 500)]
  private ?string $title = null;
  
  #[ORM\Column(length: 500, nullable: true)]
  private ?string $authors = null;
  
  #[ORM\Column(nullable: true)]
  private ?int $year = null;
  
  #[ORM\Column(length: 500, nullable: true)]
  private ?string $source = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $abstract = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $notes = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $filePath = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $originalFileName = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private array $tags = [];
  
  #[ORM\Column]
  private bool $isPublished = false;
  
  #[ORM\Column]
  private ?\DateTimeImmutable $createdAt = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updatedAt = null;
  
  #[ORM\PrePersist]
  public function onPrePersist(): void
  {
    $this->createdAt = new \DateTimeImmutable();
    $this->updatedAt = new \DateTime();
  }

  #[ORM\PreUpdate]
  public function onPreUpdate(): void
  {
    $this->updatedAt = new \DateTime();
  }

  public function getId(): ?int { return $this->id; }

  public function getTitle(): ?string { return $this->title; }
  public function setTitle(string $title): static { $this->title = $title; return $this; }
  
  public function getAuthors(): ?string { return $this->authors; }
  public function setAuthors(?string $authors): static { $this->authors = $authors; return $this; }
  
  public function getYear(): ?int { return $this->year; }
  public function setYear(?int $year): static { $this->year = $year; return $this; }
  
  public function getSource(): ?string { return $this->source; }
  public function setSource(?string $source): static { $this->source = $source; return $this; }
  
  public function getAbstract(): ?string { return $this->abstract; }
  public function setAbstract(?string $abstract): static { $this->abstract = $abstract; return $this; }
  
  public function getNotes(): ?string { return $this->notes; }
  public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

  public function getFilePath(): ?string { return $this->filePath; }
  public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }
  
  public function getOriginalFileName(): ?string { return $this->originalFileName; }
  public function setOriginalFileName(?string $name): static { $this->originalFileName = $name; return $this; }
  
  public function getTags(): array { return $this->tags; }
  public function setTags(array $tags): static { $this->tags = $tags; return $this; }
  
  public function isPublished(): bool { return $this->isPublished; }
  public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }
  
  public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
  public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
  
  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
  public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
