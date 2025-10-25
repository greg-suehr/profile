<?php

namespace App\Katzen\Service\Audit;

/**
 * Configuration for which entity fields should be audited.
 * 
 * Supports both whitelist (only track these) and blacklist (track everything except these) modes.
 */
final class AuditConfig
{
    /**
     * Fields that should NEVER be audited (security sensitive)
     */
    private const GLOBAL_BLACKLIST = [
        'password',
        'salt',
        'token',
        'secret',
        'api_key',
        'private_key',
        'ssn',
        'credit_card',
    ];

    /**
     * Per-entity configuration
     * 
     * Format:
     * 'EntityName' => [
     *     'mode' => 'whitelist' | 'blacklist',
     *     'fields' => ['field1', 'field2', ...]
     * ]
     */
    private array $config = [];

    public function __construct()
    {
        $this->initializeDefaultConfig();
    }

    private function initializeDefaultConfig(): void
    {
        // Default: audit everything except blacklisted fields
        $this->config = [
            'Customer' => [
                'mode' => 'blacklist',
                'fields' => [], // Audit all fields
            ],
            'Vendor' => [
                'mode' => 'blacklist',
                'fields' => [],
            ],
            'Order' => [
                'mode' => 'whitelist',
                'fields' => [
                    'status',
                    'fulfillment_date',
                    'delivery_date',
                    'total_amount',
                    'customer',
                ],
            ],
            'Purchase' => [
                'mode' => 'whitelist',
                'fields' => [
                    'status',
                    'expected_delivery',
                    'total_amount',
                    'vendor',
                ],
            ],
            'StockReceipt' => [
                'mode' => 'whitelist',
                'fields' => [
                    'receipt_number',
                    'received_at',
                    'received_by',
                    'status',
                    'purchase',
                    'location',
                ],
            ],
            'StockTarget' => [
                'mode' => 'whitelist',
                'fields' => [
                    'current_qty',
                    'reorder_point',
                    'status',
                ],
            ],
            'StockTransaction' => [
                'mode' => 'blacklist',
                'fields' => [], // Audit everything
            ],
            'Invoice' => [
                'mode' => 'whitelist',
                'fields' => [
                    'status',
                    'total_amount',
                    'amount_paid',
                    'amount_due',
                    'due_date',
                    'paid_date',
                ],
            ],
            'Payment' => [
                'mode' => 'blacklist',
                'fields' => ['transaction_reference'], // Don't log transaction IDs
            ],
            'LedgerEntry' => [
                'mode' => 'blacklist',
                'fields' => [], // Audit all ledger changes
            ],
        ];
    }

    /**
     * Check if a field should be audited for a given entity
     */
    public function shouldAuditField(string $entityType, string $fieldName): bool
    {
        // Never audit global blacklist fields
        if (in_array($fieldName, self::GLOBAL_BLACKLIST, true)) {
            return false;
        }

        // Skip internal Doctrine fields
        if (in_array($fieldName, ['__initializer__', '__cloner__', '__isInitialized__'], true)) {
            return false;
        }

        // Get entity config or default
        $config = $this->config[$entityType] ?? ['mode' => 'blacklist', 'fields' => []];

        if ($config['mode'] === 'whitelist') {
            // Only audit explicitly listed fields
            return in_array($fieldName, $config['fields'], true);
        }

        // Blacklist mode: audit everything except listed fields
        return !in_array($fieldName, $config['fields'], true);
    }

    /**
     * Get all auditable fields for an entity
     */
    public function getAuditableFields(string $entityType): ?array
    {
        $config = $this->config[$entityType] ?? null;
        
        if (!$config) {
            return null; // No config = audit everything
        }

        if ($config['mode'] === 'whitelist') {
            return $config['fields'];
        }

        return null; // Blacklist mode can't enumerate all possible fields
    }

    /**
     * Check if an entity type should be audited at all
     */
    public function shouldAuditEntity(string $entityType): bool
    {
        // Entities not in config are audited by default
        // You can add a 'disabled' => true flag to config if needed
        return true;
    }

    /**
     * Filter a changeset array to only include auditable fields
     */
    public function filterChangeset(string $entityType, array $changeset): array
    {
        $filtered = [];
        
        foreach ($changeset as $fieldName => $change) {
            if ($this->shouldAuditField($entityType, $fieldName)) {
                $filtered[$fieldName] = $change;
            }
        }

        return $filtered;
    }

    /**
     * Add or update entity configuration at runtime
     */
    public function configureEntity(string $entityType, string $mode, array $fields): void
    {
        if (!in_array($mode, ['whitelist', 'blacklist'], true)) {
            throw new \InvalidArgumentException("Mode must be 'whitelist' or 'blacklist'");
        }

        $this->config[$entityType] = [
            'mode' => $mode,
            'fields' => $fields,
        ];
    }
}