<?php

namespace App\Shared\Entity;

use App\Shared\Repository\SongRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Song
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'songs')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  private ?Album $album = null;

  #[ORM\Column(length: 255)]
  private ?string $title = null;

  #[ORM\Column(length: 255, unique: true)]
  private ?string $slug = null;

  #[ORM\Column(nullable: true)]
  private ?int $trackNumber = null;

  #[ORM\Column(nullable: true)]
  private ?int $durationSeconds = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $lyrics = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $image = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $audioFilePath = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $audioOriginalFileName = null;

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

  public function getAlbum(): ?Album { return $this->album; }
  public function setAlbum(?Album $album): static { $this->album = $album; return $this; }

  public function getTitle(): ?string { return $this->title; }
  public function setTitle(string $title): static { $this->title = $title; return $this; }

  public function getSlug(): ?string { return $this->slug; }
  public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

  public function getTrackNumber(): ?int { return $this->trackNumber; }
  public function setTrackNumber(?int $trackNumber): static { $this->trackNumber = $trackNumber; return $this; }

  public function getDurationSeconds(): ?int { return $this->durationSeconds; }
  public function setDurationSeconds(?int $durationSeconds): static { $this->durationSeconds = $durationSeconds; return $this; }

  public function getLyrics(): ?string { return $this->lyrics; }
  public function setLyrics(?string $lyrics): static { $this->lyrics = $lyrics; return $this; }

  public function getImage(): ?string { return $this->image; }
  public function setImage(?string $image): static { $this->image = $image; return $this; }

  public function getAudioFilePath(): ?string { return $this->audioFilePath; }
  public function setAudioFilePath(?string $audioFilePath): static { $this->audioFilePath = $audioFilePath; return $this; }

  public function getAudioOriginalFileName(): ?string { return $this->audioOriginalFileName; }
  public function setAudioOriginalFileName(?string $name): static { $this->audioOriginalFileName = $name; return $this; }

  public function getTags(): array { return $this->tags; }
  public function setTags(array $tags): static { $this->tags = $tags; return $this; }

  public function isPublished(): bool { return $this->isPublished; }
  public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }

  public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
  public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
  public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
