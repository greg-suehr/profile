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
    private ?string $status = 'active'; // 'active', inactive'

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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $address_hash = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postal_code = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $phone_digits = null;

    #[ORM\Column(nullable: true)]
    private ?array $vendor_aliases = null;

    #[ORM\Column(nullable: true)]
    private ?array $vendor_domains = null;

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

    #[ORM\PrePersist]
    public function setVendorCode(): static
    {
        $this->vendor_code =  $this->generateVendorCode(null,1);        
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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateOCRFields(): void
    {
      $this->populateOCRFields();
    }

  /**
   * Extract and populate OCR-friendly fields from existing vendor data
   */
  public function populateOCRFields(): void
  {
    if ($this->website) {
      $parsed = parse_url(strtolower($this->website));
      if (isset($parsed['host'])) {
        $domain = preg_replace('/^www\./', '', $parsed['host']);
        $this->addVendorDomain($domain);
      }
    }

    if ($this->email) {
      $domain = substr(strrchr($this->email, '@'), 1);
      if ($domain) {
        $this->addVendorDomain(strtolower($domain));
      }
    }

    if ($this->phone) {
      $digits = preg_replace('/\D/', '', $this->phone);
      $this->phone_digits = $digits;
    }

    if ($this->billing_address) {
      $this->extractPostalAndHash($this->billing_address);
    }

    if ($this->name) {
        $this->addVendorAlias($this->name);
    }
  }

  private function extractPostalAndHash(string $address): void
  {
    # USA 55555(+4444) ZIP code pattern
    if (preg_match('/\b(\d{5})(?:\-\d{4})?\b/', $address, $matches)) {
      $this->postal_code = $matches[1];
    }
    # CAD (A1A 1A1) postal code pattern
    elseif (preg_match('/\b([A-Z]\d[A-Z]\s?\d[A-Z]\d)\b/i', $address, $matches)) {
      $this->postal_code = strtoupper(str_replace(' ', '', $matches[1]));
    }

    $normalized = strtolower($address);
    $normalized = preg_replace('/[^\w\s]/', '', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim($normalized);

    # TODO: more than this
    $replacements = [
      ' street' => ' st',
      ' avenue' => ' ave',
      ' road' => ' rd',
      ' drive' => ' dr',
      ' boulevard' => ' blvd',
    ];
    $normalized = str_replace(array_keys($replacements), array_values($replacements), $normalized);
    
    $this->address_hash = hash('sha256', $normalized);
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
    #[ORM\PreUpdate]  
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

  public function generateVendorCode(
    ?string $name=null,
    int $sequence=1,
  ): string
  {
    if (is_null($name)) {
      if (is_null($this->name)) {
        return sprintf('%06d', $this->id);
      }
      
      $name = $this->name;
    }
    
    $base = trim(preg_replace('/\s+/', ' ', strtoupper($name)));
    $words = explode(' ', $base);
    
    if (count($words) === 1) {
      $code = substr($words[0], 0, 6);
    } else {
      $code = substr($words[0], 0, 4) . substr($words[1], 0, 2);
    }
    
    $code = str_pad(substr($code, 0, 6), 3, substr($words[0], -1), STR_PAD_RIGHT);
    
    $suffix = str_pad(base_convert($sequence, 10, 36), 2, '0', STR_PAD_LEFT);
    
    return $code . $suffix;
  }

  public function getAddressHash(): ?string
  {
      return $this->address_hash;
  }

  public function setAddressHash(?string $address_hash): static
  {
      $this->address_hash = $address_hash;

      return $this;
  }

  public function getPostalCode(): ?string
  {
      return $this->postal_code;
  }

  public function setPostalCode(?string $postal_code): static
  {
      $this->postal_code = $postal_code;

      return $this;
  }

  public function getPhoneDigits(): ?string
  {
      return $this->phone_digits;
  }

  public function setPhoneDigits(?string $phone_digits): static
  {
      $this->phone_digits = $phone_digits;

      return $this;
  }

  public function getVendorAliases(): ?array
  {
      return $this->vendor_aliases;
  }

  public function addVendorAlias(string $vendor_alias): static
  {
      $current_aliases = $this->vendor_aliases ?? [];
      $current_aliases[] = $vendor_alias;
      $this->vendor_aliases = array_unique($current_aliases);

      return $this;
  }

  public function removeVendorAlias(string $vendor_alias): static
  {
    $current_aliases = $this->vendor_aliases ?? [];
    $this->vendor_aliases = array_diff($current_aliases, [$vendor_alias]);

    return $this;
  }

  public function getVendorDomains(): ?array
  {
      return $this->vendor_domains;
  }

  public function addVendorDomain(string $vendor_domain): static
  {
      $current_domains = $this->vendor_domains;
      $current_domains[] = $vendor_domain;
      $this->vendor_domains = array_unique($current_domains);

      return $this;
  }

  public function removeVendorDomain(string $vendor_domain): static
  {
    $current_domains = $this->vendor_domains ?? [];
    $this->vendor_domains = array_diff($current_domains, [$vendor_domains]);

    return $this;
  }
}
