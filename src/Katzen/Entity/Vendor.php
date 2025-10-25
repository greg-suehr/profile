<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\VendorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Vendor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $vendor_code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fax = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $billing_address = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shipping_address = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tax_id = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $tax_classification = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $payment_terms = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $credit_limit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $current_balance = null;

    #[ORM\Column(length: 50)]
  private ?string $status = 'active';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, Purchase>
     */
    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'vendor')]
    private Collection $purchases;

    /**
     * @var Collection<int, StockLot>
     */
    #[ORM\OneToMany(targetEntity: StockLot::class, mappedBy: 'vendor')]
    private Collection $stockLots;

    public function __construct()
    {
        $this->purchases = new ArrayCollection();
        $this->stockLots = new ArrayCollection();
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

    public function getVendorCode(): ?string
    {
        return $this->vendor_code;
    }

    public function setVendorCode(string $vendor_code): static
    {
        $this->vendor_code = $vendor_code;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billing_address;
    }

    public function setBillingAddress(?string $billing_address): static
    {
        $this->billing_address = $billing_address;

        return $this;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shipping_address;
    }

    public function setShippingAddress(?string $shipping_address): static
    {
        $this->shipping_address = $shipping_address;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->tax_id;
    }

    public function setTaxId(?string $tax_id): static
    {
        $this->tax_id = $tax_id;

        return $this;
    }

    public function getTaxClassification(): ?string
    {
        return $this->tax_classification;
    }

    public function setTaxClassification(?string $tax_classification): static
    {
        $this->tax_classification = $tax_classification;

        return $this;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->payment_terms;
    }

    public function setPaymentTerms(?string $payment_terms): static
    {
        $this->payment_terms = $payment_terms;

        return $this;
    }

    public function getCreditLimit(): ?string
    {
        return $this->credit_limit;
    }

    public function setCreditLimit(?string $credit_limit): static
    {
        $this->credit_limit = $credit_limit;

        return $this;
    }

    public function getCurrentBalance(): ?string
    {
        return $this->current_balance;
    }

    public function setCurrentBalance(?string $current_balance): static
    {
        $this->current_balance = $current_balance;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
  
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): static
    {
        $this->created_at = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    #[ORM\PrePersist]
    #[ORM\PostPersist]  
    public function setUpdatedAt(): static
    {
        $this->updated_at = new \DateTime();

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
            $purchase->setVendor($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getVendor() === $this) {
                $purchase->setVendor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function getStockLots(): Collection
    {
        return $this->stockLots;
    }

    public function addStockLot(StockLot $stockLot): static
    {
        if (!$this->stockLots->contains($stockLot)) {
            $this->stockLots->add($stockLot);
            $stockLot->setVendor($this);
        }

        return $this;
    }

    public function removeStockLot(StockLot $stockLot): static
    {
        if ($this->stockLots->removeElement($stockLot)) {
            // set the owning side to null (unless already changed)
            if ($stockLot->getVendor() === $this) {
                $stockLot->setVendor(null);
            }
        }

        return $this;
    }
}
