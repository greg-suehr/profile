<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\SellableVariant;
use App\Katzen\Repository\CustomerPriceOverrideRepository;
use App\Katzen\Repository\PriceRuleRepository;
use App\Katzen\ValueObject\PricingContext;
use App\Katzen\ValueObject\PricingResult;

/**
 * Service for calculating prices based on Sellables, PriceRules, and CustomerPriceOverrides.
 */
class PricingService
{
    public function __construct(
        private PriceRuleRepository $priceRuleRepo,
        private CustomerPriceOverrideRepository $overrideRepo,
    ) {
    }

    /**
     * Get the final price for a Sellable in a given context
     */
    public function getSellablePrice(Sellable $sellable, PricingContext $context): string
    {
        $result = $this->getPriceBreakdown($sellable, $context);
        return $result->finalPrice;
    }

    /**
     * Get a variant-specific price
     */
    public function getVariantPrice(
        Sellable $sellable,
        SellableVariant $variant,
        PricingContext $context
    ): string {
        $baseResult = $this->getPriceBreakdown($sellable, $context);
        $basePrice = (float)$baseResult->finalPrice;
        
        // Apply variant adjustments
        $variantAdjustment = (float)($variant->getPriceAdjustment() ?? 0);
        $portionMultiplier = (float)($variant->getPortionMultiplier() ?? 1.0);
        
        $finalPrice = ($basePrice + $variantAdjustment) * $portionMultiplier;
        
        return number_format($finalPrice, 2, '.', '');
    }

    /**
     * Get detailed price breakdown with audit trail
     */
    public function getPriceBreakdown(Sellable $sellable, PricingContext $context): PricingResult
    {
        $calculations = [];
        $metadata = [];
        
        // Step 1: Start with base price
        $basePrice = (float)($sellable->getBasePrice() ?? 0.00);
        $currentPrice = $basePrice;
        
        $calculations[] = [
            'step' => 'base_price',
            'amount' => number_format($basePrice, 2, '.', ''),
            'description' => 'Base price from sellable'
        ];

        // Step 2: Check for customer-specific price overrides
        if ($context->customer) {
            $override = $this->overrideRepo->findActiveOverride(
                $context->customer,
                $sellable,
                $context->effectiveDate ?? new \DateTime()
            );
            
            if ($override) {
                $overridePrice = (float)$override->getOverridePrice();
                $calculations[] = [
                    'step' => 'customer_override',
                    'amount' => number_format($overridePrice - $currentPrice, 2, '.', ''),
                    'description' => 'Customer-specific price override',
                    'override_id' => $override->getId()
                ];
                $currentPrice = $overridePrice;
                $metadata['override_applied'] = true;
                
                // Customer overrides are exclusive - skip rule processing
                return new PricingResult(
                    finalPrice: number_format($currentPrice, 2, '.', ''),
                    basePrice: number_format($basePrice, 2, '.', ''),
                    calculations: $calculations,
                    metadata: $metadata
                );
            }
        }

        // Step 3: Apply price rules
        $effectiveDate = $context->effectiveDate ?? new \DateTime();
        $applicableRules = $this->priceRuleRepo->findApplicableRules($sellable, $effectiveDate);
        
        $exclusiveRuleApplied = false;
        
        foreach ($applicableRules as $rule) {
            if ($exclusiveRuleApplied && !$rule->isStackable()) {
                continue; // Skip non-stackable rules if an exclusive rule was applied
            }

            if (!$this->evaluateRuleConditions($rule, $context)) {
                continue; // Rule conditions not met
            }

            $priceBeforeRule = $currentPrice;
            $currentPrice = $this->applyRuleActions($rule, $currentPrice, $context);
            
            if ($currentPrice !== $priceBeforeRule) {
                $calculations[] = [
                    'step' => 'price_rule',
                    'rule_id' => $rule->getId(),
                    'rule_name' => $rule->getName(),
                    'amount' => number_format($currentPrice - $priceBeforeRule, 2, '.', ''),
                    'description' => $rule->getDescription() ?? $rule->getName()
                ];
            }

            if ($rule->isExclusive()) {
                $exclusiveRuleApplied = true;
                if (!$rule->isStackable()) {
                    break; // Stop processing rules
                }
            }
        }

        // Step 4: Apply vanity rounding (optional)
        if (isset($context->options['vanity_rounding']) && $context->options['vanity_rounding']) {
            $roundedPrice = $this->applyVanityRounding($currentPrice);
            if ($roundedPrice !== $currentPrice) {
                $calculations[] = [
                    'step' => 'vanity_rounding',
                    'amount' => number_format($roundedPrice - $currentPrice, 2, '.', ''),
                    'description' => 'Vanity price rounding'
                ];
                $currentPrice = $roundedPrice;
            }
        }

        // Ensure price doesn't go negative
        $currentPrice = max(0.00, $currentPrice);

        return new PricingResult(
            finalPrice: number_format($currentPrice, 2, '.', ''),
            basePrice: number_format($basePrice, 2, '.', ''),
            calculations: $calculations,
            metadata: $metadata
        );
    }

    /**
     * Evaluate if a price rule's conditions are met
     */
    private function evaluateRuleConditions($rule, PricingContext $context): bool
    {
        $conditions = $rule->getConditions();
        
        if (empty($conditions)) {
            return true; // No conditions means rule always applies
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$this->evaluateCondition($field, $operator, $value, $context)) {
                return false; // All conditions must be met
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(string $field, string $operator, $value, PricingContext $context): bool
    {
        $contextValue = $this->getContextValue($field, $context);

        return match ($operator) {
            '=' => $contextValue == $value,
            '!=' => $contextValue != $value,
            '>' => $contextValue > $value,
            '>=' => $contextValue >= $value,
            '<' => $contextValue < $value,
            '<=' => $contextValue <= $value,
            'in' => is_array($value) && in_array($contextValue, $value),
            'not_in' => is_array($value) && !in_array($contextValue, $value),
            'between' => is_array($value) && count($value) === 2 && $contextValue >= $value[0] && $contextValue <= $value[1],
            default => false
        };
    }

    /**
     * Extract a value from the pricing context based on field path
     */
    private function getContextValue(string $field, PricingContext $context)
    {
        return match ($field) {
            'customer.segment', 'customerSegment' => $context->customerSegment,
            'channel' => $context->channel,
            'quantity' => $context->quantity,
            'order.subtotal' => $context->order?->getSubtotal() ?? 0,
            'time.hour' => $context->effectiveTime?->format('H') ?? null,
            'time.dayOfWeek' => $context->effectiveTime?->format('N') ?? null,
            'date' => $context->effectiveDate?->format('Y-m-d') ?? null,
            default => null
        };
    }

    /**
     * Apply a price rule's actions to the current price
     */
    private function applyRuleActions($rule, float $currentPrice, PricingContext $context): float
    {
        $actions = $rule->getActions();
        
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $value = (float)($action['value'] ?? 0);

            $currentPrice = match ($type) {
                'fixed_price' => $value,
                'fixed_discount' => $currentPrice - $value,
                'percentage_discount' => $currentPrice * (1 - ($value / 100)),
                'percentage_markup' => $currentPrice * (1 + ($value / 100)),
                default => $currentPrice
            };
        }

        return $currentPrice;
    }

    /**
     * Apply vanity rounding to make prices more appealing
     * Examples: $10.03 -> $9.99, $15.47 -> $14.99
     */
    private function applyVanityRounding(float $price): float
    {
        // Round to nearest .99 ending
        $rounded = floor($price) + 0.99;
        
        // Only apply if the difference is less than $0.50
        if (abs($price - $rounded) < 0.50) {
            return $rounded;
        }
        
        return $price;
    }
}
