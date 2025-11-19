<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Customer
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;
  
  #[ORM\Column(length: 255)]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 255, unique: true)]
  #[Assert\Email]
  private ?string $email = null;
  
  #[ORM\Column(length: 20, nullable: true)]
  private ?string $phone = null;

  #[ORM\Column(length: 50)]
  private ?string $type = 'individual'; // individual, business, wholesale

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $billing_address = null;
  
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $shipping_address = null;
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $account_balance = '0.00';
  
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
  private ?string $credit_limit = null;
  
  #[ORM\Column(length: 50)]
  private ?string $status = 'active'; // active, suspended, archived

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $notes = null;

  #[ORM\Column]
  private ?\DateTimeImmutable $created_at = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updated_at = null;
  
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $last_order_at = null;

  #[ORM\OneToMany(targetEntity: CustomerPriceOverride::class, mappedBy: 'customer', orphanRemoval: true)]
  private Collection $priceOverrides;
  
  #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'customer_entity')]
  private Collection $orders;

  #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'customer')]
  private Collection $invoices;
  
  #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'customer')]
  private Collection $payments;

  #[ORM\Column(length: 50)]
  private ?string $payment_terms = 'on_receipt';

  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private ?string $ar_balance = '0.00';  
  
  public function __construct()
  {
    $this->orders = new ArrayCollection();
    $this->invoices = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->priceOverrides = new ArrayCollection();
  }

  public function __toString(): string
  {
    return $this->name ?? '';
  }

  public function getId(): ?int { return $this->id; }
  public function getName(): ?string { return $this->name; }
  public function setName(string $name): static { $this->name = $name; return $this; }
  public function getEmail(): ?string { return $this->email; }
  public function setEmail(string $email): static { $this->email = $email; return $this; }
  public function getPhone(): ?string { return $this->phone; }
  public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }  
  public function getType(): ?string { return $this->type; }
  public function setType(string $type): static { $this->type = $type; return $this; }
  public function getBillingAddress(): ?string { return $this->billing_address; }
  public function setBillingAddress(?string $billing_address): static { $this->billing_address = $billing_address; return $this; }
  public function getShippingAddress(): ?string { return $this->shipping_address; }
  public function setShippingAddress(?string $shipping_address): static { $this->shipping_address = $shipping_address; return $this; }
  public function getAccountBalance(): ?string { return $this->account_balance; }
  public function setAccountBalance(string $account_balance): static { $this->account_balance = $account_balance; return $this; }
  public function getCreditLimit(): ?string {  return $this->credit_limit; }
  public function setCreditLimit(?string $credit_limit): static {  $this->credit_limit = $credit_limit; return $this; }
  public function getStatus(): ?string { return $this->status; }
  public function isActive(): bool { return $this->status == 'inactive' ? false : true; }
  public function isSuspended(): bool { return $this->status == 'suspended'; }  
  public function setStatus(string $status): static { $this->status = $status; return $this; }
  public function getNotes(): ?string { return $this->notes; }
  public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
  public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }  

  #[ORM\PrePersist]
  public function setCreatedAt(): static { $this->created_at = new \DateTimeImmutable(); return $this; }
  public function getUpdatedAt(): ?\DateTimeInterface {  return $this->updated_at; } 

  #[ORM\PrePersist]
  #[ORM\PreUpdate]
  public function setUpdatedAt(): static { $this->updated_at = new \DateTime(); return $this; }
  public function getLastOrderAt(): ?\DateTimeInterface { return $this->last_order_at; }
  public function setLastOrderAt(?\DateTimeInterface $last_order_at): static { $this->last_order_at = $last_order_at;  return $this; }

  /**
   * @return Collection<int, Order>
   */
  public function getOrders(): Collection
  {
    return $this->orders;
  }

  public function addOrder(Order $order): static
  {
    if (!$this->orders->contains($order)) {
      $this->orders->add($order);
      $order->setCustomerEntity($this);
    }
    return $this;
  }

  public function removeOrder(Order $order): static
  {
    if ($this->orders->removeElement($order)) {
      if ($order->getCustomerEntity() === $this) {
        $order->setCustomerEntity(null);
      }
    }
    return $this;
  }

  /**
   * @return Collection<int, Invoice>
   */
  public function getInvoices(): Collection
  {
    return $this->invoices;
  }

  public function addInvoice(Invoice $invoice): static
  {
    if (!$this->invoices->contains($invoice)) {
      $this->invoices->add($invoice);
      $invoice->setCustomer($this);
    }
    return $this;
  }

  /**
   * @return Collection<int, Payment>
   */
  public function getPayments(): Collection
  {
    return $this->payments;
  }

  public function addPayment(Payment $payment): static
  {
    if (!$this->payments->contains($payment)) {
      $this->payments->add($payment);
      $payment->setCustomer($this);
    }
    return $this;
  }

  public function getPaymentTerms(): ?string
  {
      return $this->payment_terms;
  }

  public function setPaymentTerms(string $payment_terms): static
  {
      $this->payment_terms = $payment_terms;

      return $this;
  }

  public function getArBalance(): ?string
  {
      return $this->ar_balance;
  }

  public function setArBalance(string $ar_balance): static
  {
      $this->ar_balance = $ar_balance;

      return $this;
  }

  /**
   * @return Collection<int, CustomerPriceOverride>
   */
  public function getPriceOverrides(): Collection
  {
    return $this->priceOverrides;
  }

  public function addPriceOverride(CustomerPriceOverride $priceOverride): static
  {
    if (!$this->priceOverrides->contains($priceOverride)) {
      $this->priceOverrides->add($priceOverride);
        $priceOverride->setCustomer($this);
    }
    return $this;
  }

  public function removePriceOverride(CustomerPriceOverride $priceOverride): static
  {
    if ($this->priceOverrides->removeElement($priceOverride)) {
        if ($priceOverride->getCustomer() === $this) {
            $priceOverride->setCustomer(null);
        }
    }
    return $this;
  }
}
