<?php

namespace App\Shared\Entity;

use App\Shared\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Album
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
  private ?string $description = null;

  #[ORM\Column(nullable: true)]
  private ?int $releaseYear = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $image = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private array $tags = [];

  #[ORM\Column]
  private bool $isPublished = false;

  #[ORM\Column]
  private ?\DateTimeImmutable $createdAt = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updatedAt = null;

  /**
   * @var Collection<int, Song>
   */
  #[ORM\OneToMany(targetEntity: Song::class, mappedBy: 'album', orphanRemoval: true)]
  #[ORM\OrderBy(['trackNumber' => 'ASC'])]
  private Collection $songs;

  public function __construct()
  {
    $this->songs = new ArrayCollection();
  }

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

  public function getDescription(): ?string { return $this->description; }
  public function setDescription(?string $description): static { $this->description = $description; return $this; }

  public function getReleaseYear(): ?int { return $this->releaseYear; }
  public function setReleaseYear(?int $releaseYear): static { $this->releaseYear = $releaseYear; return $this; }

  public function getImage(): ?string { return $this->image; }
  public function setImage(?string $image): static { $this->image = $image; return $this; }

  public function getTags(): array { return $this->tags; }
  public function setTags(array $tags): static { $this->tags = $tags; return $this; }

  public function isPublished(): bool { return $this->isPublished; }
  public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }

  public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
  public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
  public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

  /**
   * @return Collection<int, Song>
   */
  public function getSongs(): Collection { return $this->songs; }

  public function addSong(Song $song): static
  {
    if (!$this->songs->contains($song)) {
      $this->songs->add($song);
      $song->setAlbum($this);
    }
    return $this;
  }

  public function removeSong(Song $song): static
  {
    if ($this->songs->removeElement($song)) {
      if ($song->getAlbum() === $this) {
        $song->setAlbum(null);
      }
    }
    return $this;
  }
}
