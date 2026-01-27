<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\StockLocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLocationRepository::class)]
class StockLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childLocations')]
    private ?self $parent_location = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent_location')]
    private Collection $childLocations;

    /**
     * @var Collection<int, StockLotLocationBalance>
     */
    #[ORM\OneToMany(targetEntity: StockLotLocationBalance::class, mappedBy: 'location', orphanRemoval: true)]
    private Collection $locationBalances;

    /**
     * @var Collection<int, StockLotTransfer>
     */
    #[ORM\OneToMany(targetEntity: StockLotTransfer::class, mappedBy: 'from_location')]
    private Collection $outgoing_transfers;

    /**
     * @var Collection<int, StockLotTransfer>
     */
    #[ORM\OneToMany(targetEntity: StockLotTransfer::class, mappedBy: 'to_location')]
    private Collection $incoming_transfers;

    /**
     * @var Collection<int, Purchase>
     */
    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'location')]
    private Collection $purchases;

    public function __construct()
    {
        $this->childLocations = new ArrayCollection();
        $this->locationBalances = new ArrayCollection();
        $this->outgoing_transfers = new ArrayCollection();
        $this->incoming_transfers = new ArrayCollection();
        $this->purchases = new ArrayCollection();
    }

  # TODO: move string transformation logic to a Utility Service, then document
  #       a design pattern for generate`Entity`Code() and similar utilities
  public function generateLocationCode(
    ?string $name=null,
    ?int $sequence=null,
  ): string
  {
    if (is_null($name)) {
      if (is_null($this->name)) {
        return sprintf('%03d', $this->id);
      }

      $name = $this->name;
    }

    $base = trim(preg_replace('/\s+/', ' ', strtoupper($name)));
    $words = explode(' ', $base);

    if (count($words) === 1) {
      $code = substr($words[0], 0, 3);
    } else {
      $code = substr($words[0], 0, 2) . substr($words[1], 0, 1);
    }

    if (is_null($sequence)) {
      return $code;
    } else {      
      $code = str_pad(substr($code, 0, 2), 3, substr($words[0], -1), STR_PAD_RIGHT);    
      $suffix = str_pad(base_convert($sequence, 10, 36), 2, '0', STR_PAD_LEFT);

      return $code . $suffix;
    }
  }

  # TODO: design StockLocation status
    public function getStatus(): string
    {
        return 'ok';
    }

    public function getNextExpectedDelivery(): \DateTimeInterface
    {
        return new \DateTime()->modify('+4 days');
    }  

    public function getReconciledAt(): \DateTimeInterface
    {
        return new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        if (is_null($code)) {
          $this->code =  $this->generateLocationCode(null,null);
        } else {
          $this->code = $code;
        }

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getParentLocation(): ?self
    {
        return $this->parent_location;
    }

    public function setParentLocation(?self $parent_location): static
    {
        $this->parent_location = $parent_location;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildLocations(): Collection
    {
        return $this->childLocations;
    }

    public function addChildLocation(self $childLocation): static
    {
        if (!$this->childLocations->contains($childLocation)) {
            $this->childLocations->add($childLocation);
            $childLocation->setParentLocation($this);
        }

        return $this;
    }

    public function removeChildLocation(self $childLocation): static
    {
        if ($this->childLocations->removeElement($childLocation)) {
            // set the owning side to null (unless already changed)
            if ($childLocation->getParentLocation() === $this) {
                $childLocation->setParentLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLotLocationBalance>
     */
    public function getLocationBalances(): Collection
    {
        return $this->locationBalances;
    }

    public function addLocationBalance(StockLotLocationBalance $locationBalance): static
    {
        if (!$this->locationBalances->contains($locationBalance)) {
            $this->locationBalances->add($locationBalance);
            $locationBalance->setLocation($this);
        }

        return $this;
    }

    public function removeLocationBalance(StockLotLocationBalance $locationBalance): static
    {
        if ($this->locationBalances->removeElement($locationBalance)) {
            // set the owning side to null (unless already changed)
            if ($locationBalance->getLocation() === $this) {
                $locationBalance->setLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLotTransfer>
     */
    public function getOutgoingTransfers(): Collection
    {
        return $this->outgoing_transfers;
    }

    public function addOutgoingTransfer(StockLotTransfer $outgoingTransfer): static
    {
        if (!$this->outgoing_transfers->contains($outgoingTransfer)) {
            $this->outgoing_transfers->add($outgoingTransfer);
            $outgoingTransfer->setFromLocation($this);
        }

        return $this;
    }

    public function removeOutgoingTransfer(StockLotTransfer $outgoingTransfer): static
    {
        if ($this->outgoing_transfers->removeElement($outgoingTransfer)) {
            // set the owning side to null (unless already changed)
            if ($outgoingTransfer->getFromLocation() === $this) {
                $outgoingTransfer->setFromLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLotTransfer>
     */
    public function getIncomingTransfers(): Collection
    {
        return $this->incoming_transfers;
    }

    public function addIncomingTransfer(StockLotTransfer $incomingTransfer): static
    {
        if (!$this->incoming_transfers->contains($incomingTransfer)) {
            $this->incoming_transfers->add($incomingTransfer);
            $incomingTransfer->setToLocation($this);
        }

        return $this;
    }

    public function removeIncomingTransfer(StockLotTransfer $incomingTransfer): static
    {
        if ($this->incoming_transfers->removeElement($incomingTransfer)) {
            // set the owning side to null (unless already changed)
            if ($incomingTransfer->getToLocation() === $this) {
                $incomingTransfer->setToLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->setLocation($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getLocation() === $this) {
                $purchase->setLocation(null);
            }
        }

        return $this;
    }
}
