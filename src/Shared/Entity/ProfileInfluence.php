<?php

namespace App\Shared\Entity;

use App\Shared\Repository\ProfileInfluenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileInfluenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProfileInfluence
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255)]
  private ?string $name = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $epithet = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $blurb = null;

  #[ORM\Column(length: 100, nullable: true)]
  private ?string $domain = null;

  #[ORM\Column(length: 100, nullable: true)]
  private ?string $era = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $url = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private array $tags = [];

  #[ORM\Column(nullable: true)]
  private ?int $sortOrder = null;

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

  public function getName(): ?string { return $this->name; }
  public function setName(string $name): static { $this->name = $name; return $this; }

  public function getEpithet(): ?string { return $this->epithet; }
  public function setEpithet(?string $epithet): static { $this->epithet = $epithet; return $this; }

  public function getBlurb(): ?string { return $this->blurb; }
  public function setBlurb(?string $blurb): static { $this->blurb = $blurb; return $this; }

  public function getDomain(): ?string { return $this->domain; }
  public function setDomain(?string $domain): static { $this->domain = $domain; return $this; }

  public function getEra(): ?string { return $this->era; }
  public function setEra(?string $era): static { $this->era = $era; return $this; }

  public function getUrl(): ?string { return $this->url; }
  public function setUrl(?string $url): static { $this->url = $url; return $this; }

  public function getTags(): array { return $this->tags; }
  public function setTags(array $tags): static { $this->tags = $tags; return $this; }

  public function getSortOrder(): ?int { return $this->sortOrder; }
  public function setSortOrder(?int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }

  public function isPublished(): bool { return $this->isPublished; }
  public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }

  public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
  public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
  public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
