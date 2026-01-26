<?php

namespace App\Katzen\Service\Import;

/**
 * Extraction Result Value Object
 * 
 * Immutable container for the output of a DataExtractor::extract() operation.
 * Encapsulates extracted records, diagnostic information, and any conflicts
 * or warnings encountered during extraction.
 * 
 * This is intentionally a simple value object (not a full Result/Response type)
 * because extraction is an intermediate step - the final success/failure is
 * determined during entity creation.
 * 
 * @example Usage in CatalogExtractor:
 * ```php
 * return new ExtractionResult(
 *     records: [
 *         'items' => ['latte' => ['name' => 'Latte', 'category' => 'Coffee']],
 *         'variants' => ['latte-sm' => ['base_item' => 'latte', 'size' => 'Small']],
 *     ],
 *     diagnostics: [
 *         'total_items' => 1,
 *         'total_variants' => 3,
 *         'size_patterns' => ['Sm' => 1, 'Md' => 1, 'Lg' => 1],
 *     ],
 *     warnings: ['Duplicate SKU "LATTE01" found in rows 5 and 12'],
 * );
 * ```
 */
final class ExtractionResult
{
    /**
     * @param array<string, array<string, array>> $records 
     *        Extracted records organized by entity type, keyed by natural identifier.
     *        Structure: ['entity_type' => ['natural_key' => ['field' => 'value', ...], ...], ...]
     * 
     * @param array<string, mixed> $diagnostics
     *        Statistics and metadata about the extraction for UI display.
     *        Examples: total counts, pattern frequencies, processing time
     * 
     * @param array<string> $warnings
     *        Non-fatal issues encountered during extraction.
     *        Examples: duplicate keys, missing optional fields, data quality issues
     * 
     * @param array<array{key: string, existing: array, incoming: array, resolution: string}> $conflicts
     *        Cases where incoming data conflicts with existing records.
     *        Each conflict includes the key, both values, and how it was resolved.
     * 
     * @param array<string, mixed> $metadata
     *        Additional extraction metadata for debugging/auditing.
     *        Examples: source file info, processing timestamps, extractor version
     */
    public function __construct(
        public readonly array $records = [],
        public readonly array $diagnostics = [],
        public readonly array $warnings = [],
        public readonly array $conflicts = [],
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Get records for a specific entity type.
     * 
     * @param string $entityType The entity type key (e.g., 'items', 'variants')
     * @return array<string, array> Records keyed by natural identifier
     */
    public function getRecordsForType(string $entityType): array
    {
        return $this->records[$entityType] ?? [];
    }
    
    /**
     * Get total count of all extracted records across all entity types.
     */
    public function getTotalRecordCount(): int
    {
        $total = 0;
        foreach ($this->records as $typeRecords) {
            $total += count($typeRecords);
        }
        return $total;
    }
    
    /**
     * Get count of records for a specific entity type.
     */
    public function getRecordCount(string $entityType): int
    {
        return count($this->records[$entityType] ?? []);
    }
    
    /**
     * Check if extraction produced any records.
     */
    public function hasRecords(): bool
    {
        foreach ($this->records as $typeRecords) {
            if (!empty($typeRecords)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if extraction encountered any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    /**
     * Check if extraction encountered any conflicts.
     */
    public function hasConflicts(): bool
    {
        return !empty($this->conflicts);
    }
    
    /**
     * Get a diagnostic value by key with optional default.
     */
    public function getDiagnostic(string $key, mixed $default = null): mixed
    {
        return $this->diagnostics[$key] ?? $default;
    }
    
    /**
     * Create a new result with additional records merged in.
     * 
     * Useful for combining results from multiple extraction passes
     * or adding derived records.
     */
    public function withAdditionalRecords(string $entityType, array $records): self
    {
        $newRecords = $this->records;
        $newRecords[$entityType] = array_merge(
            $newRecords[$entityType] ?? [],
            $records
        );
        
        return new self(
            records: $newRecords,
            diagnostics: $this->diagnostics,
            warnings: $this->warnings,
            conflicts: $this->conflicts,
            metadata: $this->metadata,
        );
    }
    
    /**
     * Create a new result with additional diagnostics merged in.
     */
    public function withAdditionalDiagnostics(array $diagnostics): self
    {
        return new self(
            records: $this->records,
            diagnostics: array_merge($this->diagnostics, $diagnostics),
            warnings: $this->warnings,
            conflicts: $this->conflicts,
            metadata: $this->metadata,
        );
    }
    
    /**
     * Create a new result with additional warnings appended.
     */
    public function withAdditionalWarnings(array $warnings): self
    {
        return new self(
            records: $this->records,
            diagnostics: $this->diagnostics,
            warnings: array_merge($this->warnings, $warnings),
            conflicts: $this->conflicts,
            metadata: $this->metadata,
        );
    }
    
    /**
     * Merge another ExtractionResult into this one.
     * 
     * Records are merged by entity type, with incoming records
     * overwriting existing ones with the same natural key.
     * Diagnostics are merged. Warnings and conflicts are concatenated.
     */
    public function merge(ExtractionResult $other): self
    {
        $mergedRecords = $this->records;
        foreach ($other->records as $entityType => $records) {
            $mergedRecords[$entityType] = array_merge(
                $mergedRecords[$entityType] ?? [],
                $records
            );
        }
        
        return new self(
            records: $mergedRecords,
            diagnostics: array_merge($this->diagnostics, $other->diagnostics),
            warnings: array_merge($this->warnings, $other->warnings),
            conflicts: array_merge($this->conflicts, $other->conflicts),
            metadata: array_merge($this->metadata, $other->metadata),
        );
    }
    
    /**
     * Create an empty result (useful for disabled extractors).
     */
    public static function empty(): self
    {
        return new self();
    }
    
    /**
     * Create a result with just diagnostics (no records extracted).
     */
    public static function withDiagnosticsOnly(array $diagnostics): self
    {
        return new self(diagnostics: $diagnostics);
    }
    
    /**
     * Convert to array format suitable for JSON serialization or template rendering.
     */
    public function toArray(): array
    {
        return [
            'records' => $this->records,
            'diagnostics' => $this->diagnostics,
            'warnings' => $this->warnings,
            'conflicts' => $this->conflicts,
            'metadata' => $this->metadata,
            'summary' => [
                'total_records' => $this->getTotalRecordCount(),
                'entity_types' => array_keys($this->records),
                'has_warnings' => $this->hasWarnings(),
                'has_conflicts' => $this->hasConflicts(),
            ],
        ];
    }
}
