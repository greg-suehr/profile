<?php

namespace App\Katzen\ValueObject;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\StockLocation;

/**
 * Value object that encapsulates all context needed for pricing calculations.
 */
readonly class PricingContext
{
    public function __construct(
        public ?Customer $customer = null,
        public ?string $customerSegment = null, // 'retail', 'wholesale', 'vip'
        public ?string $channel = null, // 'dine_in', 'delivery', 'catering', 'wholesale'
        public ?StockLocation $location = null,
        public float $quantity = 1.0,
        public ?\DateTimeInterface $effectiveDate = null,
        public ?\DateTimeInterface $effectiveTime = null,
        public ?Order $order = null,
        public array $options = []
    ) {
    }

    /**
     * Create a new context with modified values
     */
    public function with(array $changes): self
    {
        return new self(
            customer: $changes['customer'] ?? $this->customer,
            customerSegment: $changes['customerSegment'] ?? $this->customerSegment,
            channel: $changes['channel'] ?? $this->channel,
            location: $changes['location'] ?? $this->location,
            quantity: $changes['quantity'] ?? $this->quantity,
            effectiveDate: $changes['effectiveDate'] ?? $this->effectiveDate,
            effectiveTime: $changes['effectiveTime'] ?? $this->effectiveTime,
            order: $changes['order'] ?? $this->order,
            options: array_merge($this->options, $changes['options'] ?? [])
        );
    }

    /**
     * Create a default context for basic pricing
     */
    public static function default(): self
    {
        return new self(
            effectiveDate: new \DateTime(),
            effectiveTime: new \DateTime()
        );
    }

    /**
     * Create context from an Order
     */
    public static function fromOrder(Order $order, float $quantity = 1.0): self
    {
        return new self(
            customer: $order->getCustomerEntity(),
            order: $order,
            quantity: $quantity,
            effectiveDate: $order->getScheduledAt() ?? new \DateTime(),
            effectiveTime: $order->getScheduledAt() ?? new \DateTime()
        );
    }
}