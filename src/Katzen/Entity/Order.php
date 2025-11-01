<?php

namespace App\Katzen\Entity;

use App\Katzen\Entity\StateMachine\OrderStateMachine;
use App\Katzen\Entity\StateMachine\OrderStateException;
use App\Katzen\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The Order Entity represents customer orders.
 * 
 * Status transitions are managed through the OrderStateMachine. Use it.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customer = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Customer $customer_entity = null;

    #[ORM\Column(length: 50)]
    private string $status = OrderStateMachine::STATUS_PENDING;

    #[ORM\Column(length: 50)]
    private string $fulfillment_status = OrderStateMachine::FULFILLMENT_UNFULFILLED;

    #[ORM\Column(length: 50)]
    private string $billing_status = OrderStateMachine::BILLING_UNBILLED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archived_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduled_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fulfilled_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $voided_at = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $void_reason = null;
  
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $subtotal = '0.00';

    # TODO: not this
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $tax_rate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $tax_amount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $discount_amount = '0.00';  

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $total_amount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $invoiced_amount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $paid_amount = '0.00';
  
    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order_id', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $orderItems;
  
    /**
     * @var Collection<int, Invoice>
     */
    #[ORM\ManyToMany(targetEntity: Invoice::class, inversedBy: 'orders')]
    private Collection $invoices;

    private OrderStateMachine $stateMachine;

  public function __construct()
  {
    $this->orderItems = new ArrayCollection();
    $this->invoices = new ArrayCollection();
    $this->stateMachine = new OrderStateMachine();
  }

  #[ORM\PostLoad]
  public function initializeStateMachine(): void { $this->stateMachine = new OrderStateMachine(); } 

  public function getId(): ?int { return $this->id; }

  public function getCustomer(): ?string { return $this->customer; }
  public function setCustomer(?string $customer): static { $this->customer = $customer; return $this; }
  
  public function getCustomerEntity(): ?Customer { return $this->customer_entity; } 
  public function setCustomerEntity(?Customer $customer_entity): static {  $this->customer_entity = $customer_entity; return $this; }

  public function getNotes(): ?string {  return $this->notes; }
  public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
  
  public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; } 
  #[ORM\PrePersist]
  public function setCreatedAt(): static { $this->created_at = new \DateTime; return $this; }

  public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; } 
  #[ORM\PrePersist]
  #[ORM\PreUpdate] 
  public function setUpdatedAt(): static { $this->updated_at = new \DateTime(); return $this; }

  public function getArchivedAt(): ?\DateTimeInterface { return $this->archived_at; } 
  public function archive(): void
  {
    $this->archived_at = new \DateTime();
  }
  public function unarchive(): void
  {
    $this->archived_at = null;
  }

  public function getScheduledAt(): ?\DateTimeInterface { return $this->scheduled_at; }
  public function setScheduledAt(?\DateTimeInterface $scheduled_at): static { $this->scheduled_at = $scheduled_at; return $this; }

  public function getFulfilledAt(): ?\DateTimeInterface { return $this->fulfilled_at; }
  public function setFulfilledAt(?\DateTimeInterface $fulfilled_at): static { $this->fulfilled_at = $fulfilled_at; return $this; }

  public function getVoidedAt(): ?\DateTimeInterface { return $this->voided_at; }
  public function setVoidedAt(?\DateTimeInterface $voided_at): static { $this->voided_at = $voided_at; return $this; }
  
  public function getVoidReason(): ?string { return $this->void_reason; } 
  public function setVoidReason(string $void_reason): static { $this->void_reason = $void_reason; return $this; }

  public function getSubtotal(): ?string
  {
    return $this->subtotal;
  }

  public function getTaxAmount(): ?string
  {
    return $this->tax_amount;
  }

  public function setTaxAmount(string $tax_amount): static
  {
    $this->tax_amount = $tax_amount;
    $this->calculateTotals();
    return $this;
  }

  public function getTotalAmount(): ?string
  {
    return $this->total_amount;
  }

  public function setTotalAmount(string $total_amount): static
  {
    $this->total_amount = $total_amount;
    return $this;
  }

  /**
   * @return Collection<int, OrderItem>
   */
  public function getOrderItems(): Collection { return $this->orderItems; }
  public function addOrderItem(OrderItem $orderItem): static
  {
    if (!$this->canBeModified()) {
      throw new OrderStateException(
        "Cannot add items to order in '{$this->status}' status"
      );
    }
    
    if (!$this->orderItems->contains($orderItem)) {
      $this->orderItems->add($orderItem);
      $orderItem->setOrderId($this);
    }
    
    return $this;
  }
  public function removeOrderItem(OrderItem $orderItem): static
  {
    if (!$this->canBeModified()) {
      throw new OrderStateException(
        "Cannot remove items from order in '{$this->status}' status"
      );
    }
    
    if ($this->orderItems->removeElement($orderItem)) {
      if ($orderItem->getOrderId() === $this) {
        $orderItem->setOrderId(null);
      }
    }
    
    return $this;
  }

  /**
   * @return Collection<int, Invoice>
   */
  public function getInvoices(): Collection { return $this->invoices; } 
  public function addInvoice(Invoice $invoice): static
  {
    if (!$this->invoices->contains($invoice)) {
      $this->invoices->add($invoice);
    }
    return $this;
  }
  public function removeInvoice(Invoice $invoice): static
  {
    $this->invoices->removeElement($invoice);
    return $this;
  }

  public function calculateTotals(): void
  {
    $subtotal = 0.0;
    foreach ($this->orderItems as $item) {
      $subtotal += (float)$item->getItemSubtotal();
    }    

    $this->subtotal = number_format($subtotal, 2, '.', '');

    # TODO: order tax calculation through an ExternalTaxService interface
    # $taxAmount = $subtotal * ((float)$this->tax_rate / 100);
    # $this->tax_amount = number_format($taxAmount, 2, '.', '');
    
    $total = $subtotal + $this->tax_amount - (float)$this->discount_amount;
    $this->total_amount = number_format($total, 2, '.', '');
  }

  // ============================================
  // STATE QUERIES AND STATUS HELPERS
  // ============================================
  public function getStatus(): string
  {
    return $this->status;
  }

  public function getFulfillmentStatus(): string
  {
    return $this->fulfillment_status;
  }

  public function getBillingStatus(): string
  {
    return $this->billing_status;
  }
  
  public function getStatusLabel(): string
  {
    return $this->stateMachine->getStatusLabel($this->status);
  }

  public function getStatusBadgeClass(): string
  {
    return $this->stateMachine->getStatusBadgeClass($this->status);
  }

  public function __toString(): string
  {
    return sprintf('Order #%d (%s)', $this->id ?? 0, $this->status);
  }

  public function isArchived(): bool
  {
    return $this->archived_at !== null;
  }
  
  public function isPending(): bool
  {
    return $this->status === OrderStateMachine::STATUS_PENDING;
  }
  
  public function isOpen(): bool
  {
    return $this->status === OrderStateMachine::STATUS_OPEN;
  }

  public function isInPrep(): bool
  {
    return $this->status === OrderStateMachine::STATUS_PREP;
  }

  public function isReady(): bool
  {
    return $this->status === OrderStateMachine::STATUS_READY;
  }

  public function isClosed(): bool
  {
    return $this->status === OrderStateMachine::STATUS_CLOSED;
  }

  public function isVoided(): bool
  {
    return $this->status === OrderStateMachine::STATUS_VOIDED;
  }

  public function isCancelled(): bool
  {
    return $this->status === OrderStateMachine::STATUS_CANCELLED;
  }

  public function isTerminal(): bool
  {
    return $this->stateMachine->isTerminalStatus($this->status);
  }

  public function isFullyFulfilled(): bool
  {
    return $this->fulfillment_status === OrderStateMachine::FULFILLMENT_COMPLETE;
  }

  public function isFullyPaid(): bool
  {
    return $this->billing_status === OrderStateMachine::BILLING_PAID;
  }

  public function canBeModified(): bool
  {
    return !$this->isTerminal() && 
           $this->fulfillment_status === OrderStateMachine::FULFILLMENT_UNFULFILLED;
  }

  /**
   * Transition to a new status
   * 
   * @throws OrderStateException if transition is invalid
   */
  public function transitionTo(string $newStatus): void
  {
    $this->stateMachine->validateTransition(
      $this->status,
      $newStatus,
      $this->fulfillment_status,
      $this->billing_status,
      !$this->orderItems->isEmpty()
    );

    $this->status = $newStatus;

    if ($newStatus === OrderStateMachine::STATUS_VOIDED) {
      $this->voided_at = new \DateTime();
    }
  }

  /**
   * Open the order. This makes it available to Prep and Production workflows and
   * messages StockReservations for the stock required to fulfill its OrderItems.
   */
  public function open(): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_OPEN);
  }

  /**
   * Move order to preparation.
   */
  public function startPrep(): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_PREP);
  }

  /**
   * Mark order as ready for fulfillment. This makes it available to PackShip and
   * Serve workflows.
   */
  public function markReady(): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_READY);
  }

  /**
   * Close the order. This is a "happy path" terminal state for Orders, which
   * indicates the order was (at least partially) fulfilled and paid or
   * 
   */
  public function close(): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_CLOSED);
  }

  /**
   * Cancel the order. This is a neutral terminal state for Orders, which
   * indicates the order is no longer needed, and that this designation
   * occured before any significant transactional activity occurred.
   * 
   * @throws OrderStateException if order cannot be cancelled
   */
  public function cancel(): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_CANCELLED);
  }
  
  /**
   * Void the order. This is a "sad path" terminal state of Orders, which
   * indicates the order is no longer needed, and that this designation
   * occured after some significant transaction activity occurred, which
   * likely needs to be reversed or acknowledged with a reason code.
   * 
   * @throws OrderStateException if order cannot be voided
   */
  public function void(string $reason): void
  {
    $this->transitionTo(OrderStateMachine::STATUS_VOIDED);
    $this->void_reason = $reason;
  }
  
  /**
   * Get valid next states from current state
   */
  public function getAvailableTransitions(): array
  {
    return $this->stateMachine->getValidNextStates($this->status);
  }

  /**
   * Update fulfillment status based on fulfilled items
   * This is called automatically when items are fulfilled
   */
  public function updateFulfillmentStatus(): void
  {
    $totalItems = $this->orderItems->count();
    $fulfilledItems = $this->orderItems->filter(
      fn(OrderItem $item) => $item->isFulfilled()
        )->count();
    
    $newStatus = match(true) {
      $fulfilledItems === 0 => OrderStateMachine::FULFILLMENT_UNFULFILLED,
      $fulfilledItems === $totalItems => OrderStateMachine::FULFILLMENT_COMPLETE,
      default => OrderStateMachine::FULFILLMENT_PARTIAL,
    };
    
    if ($newStatus === $this->fulfillment_status) {
      return; // No change needed
    }
    
    $this->stateMachine->validateFulfillmentTransition(
      $this->fulfillment_status,
      $newStatus,
      $totalItems,
      $fulfilledItems
    );

    $this->fulfillment_status = $newStatus;
    
    if ($newStatus === OrderStateMachine::FULFILLMENT_COMPLETE) {
      $this->fulfilled_at = new \DateTime();
    } else {
      $this->fulfilled_at = null;
    }
  }

  /**
   * Mark all items as fulfilled
   */
  public function fulfillAll(): void
  {
    foreach ($this->orderItems as $item) {
      if (!$item->isFulfilled()) {
        $item->fulfill();
      }
    }
    $this->updateFulfillmentStatus();
  }

  /**
   * Update billing status based on invoiced and paid amounts
   * This is typically called by the accounting service
   */
  public function updateBillingStatus(): void
  {
    $total = (float) $this->total_amount;
    $invoiced = (float) $this->invoiced_amount;
    $paid = (float) $this->paid_amount;
    
    $newStatus = match(true) {
      $paid >= $total && $paid > 0 => OrderStateMachine::BILLING_PAID,
      $paid > 0 && $paid < $total => OrderStateMachine::BILLING_PARTIAL,
      $invoiced > 0 => OrderStateMachine::BILLING_INVOICED,
      default => OrderStateMachine::BILLING_UNBILLED,
    };

    if ($newStatus === $this->billing_status) {
      return;
    }
    
    $this->stateMachine->validateBillingTransition(
      $this->billing_status,
      $newStatus,
      $total,
      $invoiced,
      $paid
    );
    
    $this->billing_status = $newStatus;
  }

  /**
   * Record that an invoice has been created for this order
   */
  public function recordInvoice(float $amount): void
  {
    $this->invoiced_amount = number_format(
      (float)$this->invoiced_amount + $amount,
      2,
      '.',
      ''
    );
    $this->updateBillingStatus();
  }

  /**
   * Record a payment against this order
   */
  public function recordPayment(float $amount): void
  {
    $this->paid_amount = number_format(
      (float)$this->paid_amount + $amount,
      2,
      '.',
      ''
    );
    $this->updateBillingStatus();
  }

  /**
   * Process a refund
   */
  public function refund(float $amount): void
  {
    $paid = (float) $this->paid_amount;
        
    if ($amount > $paid) {
      throw new OrderStateException(
        "Cannot refund \${$amount} when only \${$paid} has been paid"
      );
    }
    
    $this->paid_amount = number_format($paid - $amount, 2, '.', '');
        
    if ((float)$this->paid_amount === 0.0) {
      $this->billing_status = OrderStateMachine::BILLING_REFUNDED;
    } else {
      $this->updateBillingStatus();
    }
  }

  public function getInvoicedAmount(): string
  {
    return $this->invoiced_amount;
  }

  public function getPaidAmount(): string
  {
    return $this->paid_amount;
  }

  public function getRemainingBalance(): float
  {
    return (float)$this->total_amount - (float)$this->paid_amount;
  }
}
