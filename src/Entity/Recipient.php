<?php

namespace App\Entity;

use App\Repository\RecipientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipientRepository::class)]
class Recipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $source = null;

    /**
     * @var Collection<int, RecipientEvent>
     */
    #[ORM\OneToMany(targetEntity: RecipientEvent::class, mappedBy: 'y', orphanRemoval: true)]
    private Collection $recipientEvents;

    public function __construct()
    {
        $this->recipientEvents = new ArrayCollection();
    }

  public function __toString(): string
  {
    return $this->name;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return Collection<int, RecipientEvent>
     */
    public function getRecipientEvents(): Collection
    {
        return $this->recipientEvents;
    }

    public function addRecipientEvent(RecipientEvent $recipientEvent): static
    {
        if (!$this->recipientEvents->contains($recipientEvent)) {
            $this->recipientEvents->add($recipientEvent);
            $recipientEvent->setY($this);
        }

        return $this;
    }

    public function removeRecipientEvent(RecipientEvent $recipientEvent): static
    {
        if ($this->recipientEvents->removeElement($recipientEvent)) {
            // set the owning side to null (unless already changed)
            if ($recipientEvent->getY() === $this) {
                $recipientEvent->setY(null);
            }
        }

        return $this;
    }
}
