<?php

namespace App\Profile\Entity;

use App\Profile\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORV\HasLifecycleCallbacks]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    public function __toString(): string
    {
        return $this->name;
    }

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, options: ["jsonb" => true])]
    private array $schema = [];

    /**
     * @var Collection<int, Content>
     */
    #[ORM\OneToMany(targetEntity: Content::class, mappedBy: 'category', orphanRemoval: true)]
    private Collection $contents;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function __construct()
    {
        $this->contents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function setSchema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

  /**
   * @var FieldDefinition[]
   * @ORM\Transient()   // Doctrine will ignore this entirely
   */
  private array $schemaDefinitions = [];
  
  public function getSchemaDefinitions(): array
  {
        return $this->schemaDefinitions;
    }
  
  public function setSchemaDefinitions(array $definitions): self
  {
        $this->schemaDefinitions = $definitions;
        return $this;
    }


    /**
     * @return Collection<int, Content>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function addContent(Content $content): static
    {
        if (!$this->contents->contains($content)) {
            $this->contents->add($content);
            $content->setCategory($this);
        }

        return $this;
    }

    public function removeContent(Content $content): static
    {
        if ($this->contents->removeElement($content)) {
            // set the owning side to null (unless already changed)
            if ($content->getCategory() === $this) {
                $content->setCategory(null);
            }
        }

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
     	$this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PostPersist]
    public function onPostPersist(): void
    {
        $this->updated_at = new \DateTime();
    }
}
