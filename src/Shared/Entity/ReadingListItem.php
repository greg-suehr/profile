<?php

namespace App\Shared\Entity;

use App\Shared\Repository\ReadingListItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReadingListItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReadingListItem
{
  public const STATUS_WANT    = 'want_to_read';
  public const STATUS_READING = 'reading';
  public const STATUS_DONE    = 'completed';

  public const STATUSES = [
    'Want to Read' => self::STATUS_WANT,
    'Reading'      => self::STATUS_READING,
    'Completed'    => self::STATUS_DONE,
  ];
  
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\Column(length: 500)]
  private ?string $title = null;
  
  #[ORM\Column(length: 500, nullable: true)]
  private ?string $author = null;
  
  #[ORM\Column(nullable: true)]
  private ?int $year = null;
  
  #[ORM\Column(length: 20, nullable: true)]
  private ?string $isbn = null;
  
  #[ORM\Column(length: 20)]
  private string $status = self::STATUS_WANT;

  #[ORM\Column(nullable: true)]
  private ?int $rating = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $notes = null;

  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $dateFinished = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private array $tags = [];

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $coverUrl = null;

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

  public function getStatusLabel(): string
  {
    return array_search($this->status, self::STATUSES) ?: $this->status;
  }

  public function getId(): ?int { return $this->id; }
  
  public function getTitle(): ?string { return $this->title; }
  public function setTitle(string $title): static { $this->title = $title; return $this; }
  
  public function getAuthor(): ?string { return $this->author; }
  public function setAuthor(?string $author): static { $this->author = $author; return $this; }
  
  public function getYear(): ?int { return $this->year; }
  public function setYear(?int $year): static { $this->year = $year; return $this; }
  
  public function getIsbn(): ?string { return $this->isbn; }
  public function setIsbn(?string $isbn): static { $this->isbn = $isbn; return $this; }
  
  public function getStatus(): string { return $this->status; }
  public function setStatus(string $status): static { $this->status = $status; return $this; }
  
  public function getRating(): ?int { return $this->rating; }
  public function setRating(?int $rating): static { $this->rating = $rating; return $this; }
  
  public function getNotes(): ?string { return $this->notes; }
  public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
  
  public function getDateFinished(): ?\DateTimeInterface { return $this->dateFinished; }
  public function setDateFinished(?\DateTimeInterface $dateFinished): static { $this->dateFinished = $dateFinished; return $this; }
  
  public function getTags(): array { return $this->tags; }
  public function setTags(array $tags): static { $this->tags = $tags; return $this; }
  
  public function getCoverUrl(): ?string { return $this->coverUrl; }
  public function setCoverUrl(?string $coverUrl): static { $this->coverUrl = $coverUrl; return $this; }
  
  public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
  public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
  
  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
  public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
