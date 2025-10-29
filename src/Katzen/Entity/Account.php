<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childAccounts')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $childAccounts;

    /**
     * @var Collection<int, LedgerEntryLine>
     */
    #[ORM\OneToMany(targetEntity: LedgerEntryLine::class, mappedBy: 'account')]
    private Collection $subledgerTransactions;

    public function __construct()
    {
        $this->childAccounts = new ArrayCollection();
        $this->subledgerTransactions = new ArrayCollection();
    }

  public function __toString()
  {
    return $this->code . " - " . $this->name;
  }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildAccounts(): Collection
    {
        return $this->childAccounts;
    }

    public function addChildAccount(self $childAccount): static
    {
        if (!$this->childAccounts->contains($childAccount)) {
            $this->childAccounts->add($childAccount);
            $childAccount->setParent($this);
        }

        return $this;
    }

    public function removeChildAccount(self $childAccount): static
    {
        if ($this->childAccounts->removeElement($childAccount)) {
            // set the owning side to null (unless already changed)
            if ($childAccount->getParent() === $this) {
                $childAccount->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LedgerEntryLine>
     */
    public function getSubledgerTransactions(): Collection
    {
        return $this->subledgerTransactions;
    }

    public function addSubledgerTransaction(LedgerEntryLine $subledgerTransaction): static
    {
        if (!$this->subledgerTransactions->contains($subledgerTransaction)) {
            $this->subledgerTransactions->add($subledgerTransaction);
            $subledgerTransaction->setAccount($this);
        }

        return $this;
    }

    public function removeSubledgerTransaction(LedgerEntryLine $subledgerTransaction): static
    {
        if ($this->subledgerTransactions->removeElement($subledgerTransaction)) {
            // set the owning side to null (unless already changed)
            if ($subledgerTransaction->getAccount() === $this) {
                $subledgerTransaction->setAccount(null);
            }
        }

        return $this;
    }
}
