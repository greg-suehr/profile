<?php

namespace App\Shared\Entity;

use App\Shared\Repository\DocumentViewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentViewRepository::class)]
#[ORM\Index(columns: ['document_id'], name: 'idx_docview_document')]
#[ORM\Index(columns: ['viewed_at'], name: 'idx_docview_date')]
class DocumentView
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\ManyToOne(targetEntity: ResearchDocument::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?ResearchDocument $document = null;

  #[ORM\Column]
  private ?\DateTimeImmutable $viewedAt = null;

  #[ORM\Column(length: 45, nullable: true)]
  private ?string $ipAddress = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $userAgent = null;

  #[ORM\Column(length: 500, nullable: true)]
  private ?string $referer = null;

  public function getId(): ?int { return $this->id; }

  public function getDocument(): ?ResearchDocument { return $this->document; }
  public function setDocument(?ResearchDocument $document): static { $this->document = $document; return $this; }

  public function getViewedAt(): ?\DateTimeImmutable { return $this->viewedAt; }
  public function setViewedAt(\DateTimeImmutable $viewedAt): static { $this->viewedAt = $viewedAt; return $this; }

  public function getIpAddress(): ?string { return $this->ipAddress; }
  public function setIpAddress(?string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

  public function getUserAgent(): ?string { return $this->userAgent; }
  public function setUserAgent(?string $userAgent): static { $this->userAgent = $userAgent; return $this; }

  public function getReferer(): ?string { return $this->referer; }
  public function setReferer(?string $referer): static { $this->referer = $referer; return $this; }
}