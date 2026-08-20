<?php

namespace App\Shared\Entity;

use App\Shared\Repository\PlayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Play
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255)]
  private ?string $title = null;

  #[ORM\Column(length: 255, unique: true)]
  private ?string $slug = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $blurb = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $description = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $genre = null;

  #[ORM\Column(nullable: true)]
  private ?int $runtimeMinutes = null;

  #[ORM\Column(nullable: true)]
  private ?int $premiereYear = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $image = null;

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

  public function getSlug(): ?string { return $this->slug; }
  public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

  public function getBlurb(): ?string { return $this->blurb; }
  public function setBlurb(?string $blurb): static { $this->blurb = $blurb; return $this; }

  public function getDescription(): ?string { return $this->description; }
  public function setDescription(?string $description): static { $this->description = $description; return $this; }

  public function getGenre(): ?string { return $this->genre; }
  public function setGenre(?string $genre): static { $this->genre = $genre; return $this; }

  public function getRuntimeMinutes(): ?int { return $this->runtimeMinutes; }
  public function setRuntimeMinutes(?int $runtimeMinutes): static { $this->runtimeMinutes = $runtimeMinutes; return $this; }

  public function getPremiereYear(): ?int { return $this->premiereYear; }
  public function setPremiereYear(?int $premiereYear): static { $this->premiereYear = $premiereYear; return $this; }

  public function getImage(): ?string { return $this->image; }
  public function setImage(?string $image): static { $this->image = $image; return $this; }

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
