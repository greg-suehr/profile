<?php

namespace App\Katzen\Entity\StateMachine;

/**
 * Order State Machine
 * 
 * Manages valid state transitions and business rules for Order lifecycle.
 */
final class OrderStateMachine
{
    # `order.status`
    public const STATUS_PENDING = 'pending';
    public const STATUS_OPEN = 'open';
    public const STATUS_PREP = 'prep';
    public const STATUS_READY = 'ready';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_CANCELLED = 'cancelled';
    
    # `order.fulfillment_status`
    public const FULFILLMENT_UNFULFILLED = 'unfulfilled';
    public const FULFILLMENT_PARTIAL = 'partial';
    public const FULFILLMENT_COMPLETE = 'complete';
    
    # `order.billing_status`
    public const BILLING_UNBILLED = 'unbilled';
    public const BILLING_INVOICED = 'invoiced';
    public const BILLING_PARTIAL = 'partial';
    public const BILLING_PAID = 'paid';
    public const BILLING_REFUNDED = 'refunded';
    
    /**
     * Valid state transitions map
     * Format: 'current_status' => ['allowed', 'next', 'statuses']
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_OPEN,
            self::STATUS_CANCELLED,
            self::STATUS_VOIDED,
        ],
        self::STATUS_OPEN => [
            self::STATUS_PREP,
            self::STATUS_CANCELLED,
            self::STATUS_VOIDED,
        ],
        self::STATUS_PREP => [
            self::STATUS_READY,
            self::STATUS_OPEN,
            self::STATUS_VOIDED,
        ],
        self::STATUS_READY => [
            self::STATUS_CLOSED,
            self::STATUS_OPEN,            
            self::STATUS_PREP,
            self::STATUS_VOIDED,
        ],
        self::STATUS_CLOSED => [
            self::STATUS_VOIDED,
        ],
        self::STATUS_VOIDED => [],
        self::STATUS_CANCELLED => [],
    ];
    
    /**
     * Valid fulfillment status transitions
     */
    private const VALID_FULFILLMENT_TRANSITIONS = [
        self::FULFILLMENT_UNFULFILLED => [
            self::FULFILLMENT_PARTIAL,
            self::FULFILLMENT_COMPLETE,
        ],
        self::FULFILLMENT_PARTIAL => [
            self::FULFILLMENT_COMPLETE,
            self::FULFILLMENT_UNFULFILLED,
        ],
        self::FULFILLMENT_COMPLETE => [
            self::FULFILLMENT_PARTIAL,
            self::FULFILLMENT_UNFULFILLED,            
        ],
    ];
    
    /**
     * Valid billing status transitions
     */
    private const VALID_BILLING_TRANSITIONS = [
        self::BILLING_UNBILLED => [
            self::BILLING_INVOICED,
            self::BILLING_PARTIAL,
            self::BILLING_PAID,
        ],
        self::BILLING_INVOICED => [
            self::BILLING_PARTIAL,
            self::BILLING_PAID,
            self::BILLING_REFUNDED,
        ],
        self::BILLING_PARTIAL => [
            self::BILLING_PAID,
            self::BILLING_INVOICED,
            self::BILLING_REFUNDED,            
        ],
        self::BILLING_PAID => [
            self::BILLING_REFUNDED,
            self::BILLING_PARTIAL,
        ],
        self::BILLING_REFUNDED => [],
    ];
    
  /**
   * Check if a status transition is valid
   */
  public function canTransitionTo(string $currentStatus, string $newStatus): bool
  {
    if (!isset(self::VALID_TRANSITIONS[$currentStatus])) {
      return false;
    }
        
    return in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus], true);
  }
  
  /**
   * Check if a fulfillment status transition is valid
   */
  public function canTransitionFulfillmentTo(string $currentStatus, string $newStatus): bool
  {
    if (!isset(self::VALID_FULFILLMENT_TRANSITIONS[$currentStatus])) {
      return false;
    }
        
    return in_array($newStatus, self::VALID_FULFILLMENT_TRANSITIONS[$currentStatus], true);
  }
    
  /**
   * Check if a billing status transition is valid
   */
  public function canTransitionBillingTo(string $currentStatus, string $newStatus): bool
  {
    if (!isset(self::VALID_BILLING_TRANSITIONS[$currentStatus])) {
        return false;
    }
        
    return in_array($newStatus, self::VALID_BILLING_TRANSITIONS[$currentStatus], true);
  }
    
  /**
   * Get all valid next states from current status
   */
  public function getValidNextStates(string $currentStatus): array
  {
    return self::VALID_TRANSITIONS[$currentStatus] ?? [];
  }
    
  /**
   * Check if status is a terminal state (no further transitions allowed)
   */
  public function isTerminalStatus(string $status): bool
  {
    return in_array($status, [self::STATUS_VOIDED, self::STATUS_CANCELLED], true);
  }
    
  /**
   * Validate business rules for transitioning to a new status
   * 
   * @throws OrderStateException if transition would violate business rules
   */
  public function validateTransition(
    string $currentStatus,
    string $newStatus,
    string $fulfillmentStatus,
    string $billingStatus,
    bool $hasItems = true
  ): void {
    if (!$this->canTransitionTo($currentStatus, $newStatus)) {
      throw new OrderStateException(
        "Cannot transition from '{$currentStatus}' to '{$newStatus}'"
      );
    }
        
    // Cannot move to PREP without items
    if ($newStatus === self::STATUS_PREP && !$hasItems) {
      throw new OrderStateException(
        "Cannot move order to prep status without order items"
      );
    }
    
    // Cannot close without complete fulfillment
    if ($newStatus === self::STATUS_CLOSED && $fulfillmentStatus !== self::FULFILLMENT_COMPLETE) {
      throw new OrderStateException(
        "Cannot close order until fulfillment is complete"
      );
    }
    
    // Cannot close without being invoiced or paid
    if ($newStatus === self::STATUS_CLOSED && 
        !in_array($billingStatus, [self::BILLING_INVOICED, self::BILLING_PARTIAL, self::BILLING_PAID], true)) {
      throw new OrderStateException(
        "Cannot close order until it has been billed"
      );
    }
        
    // Cannot void or cancel if already paid
    if (in_array($newStatus, [self::STATUS_VOIDED, self::STATUS_CANCELLED], true) &&
        $billingStatus === self::BILLING_PAID) {
      throw new OrderStateException(
        "Cannot void or cancel a paid order. Issue a refund first."
      );
    }
  }
    
  /**
   * Validate fulfillment status transition business rules
   * 
   * @throws OrderStateException if transition would violate business rules
   */
  public function validateFulfillmentTransition(
    string $currentStatus,
    string $newStatus,
    int $totalItems,
    int $fulfilledItems
  ): void {
    if (!$this->canTransitionFulfillmentTo($currentStatus, $newStatus)) {
      throw new OrderStateException(
        "Cannot transition fulfillment from '{$currentStatus}' to '{$newStatus}'"
      );
    }
        
    // Partial must have some but not all items fulfilled
    if ($newStatus === self::FULFILLMENT_PARTIAL) {
      if ($fulfilledItems === 0) {
        throw new OrderStateException(
          "Cannot set to partial fulfillment with no items fulfilled"
        );
      }
      if ($fulfilledItems >= $totalItems) {
        throw new OrderStateException(
          "Cannot set to partial fulfillment when all items are fulfilled. Use complete instead."
        );
      }
    }
    
    // Complete must have all items fulfilled
    if ($newStatus === self::FULFILLMENT_COMPLETE && $fulfilledItems < $totalItems) {
      throw new OrderStateException(
        "Cannot set to complete fulfillment when only {$fulfilledItems} of {$totalItems} items are fulfilled"
      );
    }
    
    // Unfulfilled must have no items fulfilled
    if ($newStatus === self::FULFILLMENT_UNFULFILLED && $fulfilledItems > 0) {
      throw new OrderStateException(
        "Cannot set to unfulfilled when {$fulfilledItems} items are already fulfilled"
      );
    }
  }
    
  /**
   * Validate billing status transition business rules
   * 
   * @throws OrderStateException if transition would violate business rules
   */
  public function validateBillingTransition(
    string $currentStatus,
    string $newStatus,
    float $totalAmount,
    float $invoicedAmount,
    float $paidAmount
  ): void {
    if (!$this->canTransitionBillingTo($currentStatus, $newStatus)) {
      throw new OrderStateException(
        "Cannot transition billing from '{$currentStatus}' to '{$newStatus}'"
      );
    }
    
    // Cannot invoice without a total amount
    if ($newStatus === self::BILLING_INVOICED && $totalAmount <= 0) {
      throw new OrderStateException(
        "Cannot invoice an order with zero or negative total"
      );
    }
    
    // Partial payment validation
    if ($newStatus === self::BILLING_PARTIAL) {
      if ($paidAmount <= 0) {
        throw new OrderStateException(
          "Cannot set to partial payment with no amount paid"
        );
      }
      if ($paidAmount >= $totalAmount) {
        throw new OrderStateException(
          "Cannot set to partial payment when full amount is paid. Use paid instead."
        );
      }
    }
    
    // Paid validation
    if ($newStatus === self::BILLING_PAID && $paidAmount < $totalAmount) {
      throw new OrderStateException(
        "Cannot set to paid when only \${$paidAmount} of \${$totalAmount} is paid"
      );
    }
  }
  
  /**
   * Get human-readable status label
   */
  public function getStatusLabel(string $status): string
  {
    return match($status) {
      self::STATUS_PENDING => 'Pending',
      self::STATUS_OPEN => 'Open',
      self::STATUS_PREP => 'In Preparation',
      self::STATUS_READY => 'Ready',
      self::STATUS_CLOSED => 'Closed',
      self::STATUS_VOIDED => 'Voided',
      self::STATUS_CANCELLED => 'Cancelled',
      default => ucfirst($status),
    };
  }
    
  /**
   * Get CSS class for status badge
   */
  public function getStatusBadgeClass(string $status): string
  {
    return match($status) {
      self::STATUS_PENDING => 'warning',
      self::STATUS_OPEN => 'info',
      self::STATUS_PREP => 'primary',
      self::STATUS_READY => 'success',
      self::STATUS_CLOSED => 'secondary',
      self::STATUS_VOIDED, self::STATUS_CANCELLED => 'danger',
      default => 'secondary',
    };
  }
}

/**
 * Exception thrown when an invalid state transition is attempted
 */
class OrderStateException extends \DomainException
{
  
}
