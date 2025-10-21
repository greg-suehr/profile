<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\LedgerEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LedgerEntryRepository::class)]
class LedgerEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(length: 255)]
    private ?string $transaction_type = null;

    #[ORM\Column(length: 255)]
    private ?string $reference_type = null;

    #[ORM\Column(length: 255)]
    private ?string $reference_id = null;

    #[ORM\Column]
    private ?bool $is_reconciled = null;

    /**
     * @var Collection<int, LedgerEntryLine>
     */
    #[ORM\OneToMany(targetEntity: LedgerEntryLine::class, mappedBy: 'entry', orphanRemoval: true, cascade: ["persist"])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getTransactionType(): ?string
    {
        return $this->transaction_type;
    }

    public function setTransactionType(string $transaction_type): static
    {
        $this->transaction_type = $transaction_type;

        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->reference_type;
    }

    public function setReferenceType(string $reference_type): static
    {
        $this->reference_type = $reference_type;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->reference_id;
    }

    public function setReferenceId(string $reference_id): static
    {
        $this->reference_id = $reference_id;

        return $this;
    }

    public function isReconciled(): ?bool
    {
        return $this->is_reconciled;
    }

    public function setIsReconciled(bool $is_reconciled): static
    {
        $this->is_reconciled = $is_reconciled;

        return $this;
    }

    /**
     * @return Collection<int, LedgerEntryLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(LedgerEntryLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setEntry($this);
        }

        return $this;
    }

    public function removeLine(LedgerEntryLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getEntry() === $this) {
                $line->setEntry(null);
            }
        }

        return $this;
    }
}
