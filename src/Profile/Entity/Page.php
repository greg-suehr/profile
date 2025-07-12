<?php

namespace App\Profile\Entity;

use App\Profile\Repository\PageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::JSON, options: ["jsonb" => true])]
    private array $data = [];

    private ?string $htmlContent = null;
    public function getHtmlContent(): string
    {
        return $this->data['html'] ?? '';
    }

    public function setHtmlContent(string $html): self
    {
        $this->htmlContent  = $html;
        $this->data['html'] = $html;
        return $this;
    }

    #[ORM\Column]
    private ?bool $is_published = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(length: 255)]
    private ?string $page_type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->is_published;
    }

    public function setIsPublished(bool $is_published): static
    {
        $this->is_published = $is_published;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getPageType(): ?string
    {
        return $this->page_type;
    }

    public function setPageType(string $page_type): static
    {
        $this->page_type = $page_type;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
     	$this->created_at = new \DateTimeImmutable();
     	$this->updated_at = new \DateTime();
     	$this->page_type = 'page';
    }

    #[ORM\PostPersist]
    public function onPostPersist(): void
    {
    	$this->updated_at = new \DateTime();
    }
}
