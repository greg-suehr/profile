<?php

namespace App\Katzen\Service\Import\Extractor;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Import\DataExtractor;
use App\Katzen\Service\Import\ExtractionResult;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract Data Extractor
 * 
 * Base class for DataExtractor implementations providing common
 * functionality for header matching, data extraction, and entity creation.
 * 
 * Concrete extractors extend this class and implement:
 * - getDetectionHeaders(): Headers that indicate this extractor is relevant
 * - getStrongIndicators(): Headers that strongly suggest this data type
 * - extractRecords(): Core extraction logic for this domain
 * - createEntities(): Entity persistence logic
 */
abstract class AbstractDataExtractor implements DataExtractor
{
    protected const DEFAULT_PRIORITY = 100;
    
    public function __construct(
        protected EntityManagerInterface $em,
        protected LoggerInterface $logger,
    ) {}
    
    /**
     * Get headers that must be present (at least one) for relevance.
     * 
     * @return array<array<string>> Groups of alternative header names
     */
    abstract protected function getDetectionHeaders(): array;
    
    /**
     * Get headers that strongly indicate this data type.
     * 
     * @return array<string> List of indicative header names
     */
    abstract protected function getStrongIndicators(): array;
    
    /**
     * Core extraction logic - implement in concrete class.
     */
    abstract protected function extractRecords(
        array $rows,
        array $headers,
        ImportMapping $mapping
    ): ExtractionResult;
    
    /**
     * Default detection implementation using header matching.
     * 
     * Override for more sophisticated detection logic.
     */
    public function detect(array $headers, array $sampleRows): float
    {
        $score = 0.0;
        $normalizedHeaders = $this->normalizeHeaders($headers);
        
        // Check required headers (must have at least one group present)
        $detectionHeaders = $this->getDetectionHeaders();
        $hasRequiredHeader = false;
        
        foreach ($detectionHeaders as $headerGroup) {
            if ($this->hasAnyHeader($normalizedHeaders, $headerGroup)) {
                $hasRequiredHeader = true;
                $score += 0.4 / count($detectionHeaders);
            }
        }
        
        // If no required headers found, this extractor isn't relevant
        if (!$hasRequiredHeader && !empty($detectionHeaders)) {
            return 0.0;
        }
        
        // Add score for strong indicators
        $strongIndicators = $this->getStrongIndicators();
        $matchedIndicators = 0;
        
        foreach ($strongIndicators as $indicator) {
            if ($this->hasHeader($normalizedHeaders, $indicator)) {
                $matchedIndicators++;
            }
        }
        
        if (!empty($strongIndicators)) {
            $indicatorScore = ($matchedIndicators / count($strongIndicators)) * 0.5;
            $score += $indicatorScore;
        }
        
        // Allow subclasses to add data-based scoring
        $dataScore = $this->scoreFromData($normalizedHeaders, $sampleRows);
        $score += $dataScore;
        
        return min($score, 1.0);
    }
    
    /**
     * Delegate to extractRecords and wrap with logging.
     */
    public function extract(array $rows, array $headers, ImportMapping $mapping): ExtractionResult
    {
        $startTime = microtime(true);
        
        $this->logger->debug('Starting extraction', [
            'extractor' => $this->getLabel(),
            'row_count' => count($rows),
            'header_count' => count($headers),
        ]);
        
        try {
            $result = $this->extractRecords($rows, $headers, $mapping);
            
            $elapsed = microtime(true) - $startTime;
            
            $this->logger->info('Extraction complete', [
                'extractor' => $this->getLabel(),
                'total_records' => $result->getTotalRecordCount(),
                'warnings' => count($result->warnings),
                'conflicts' => count($result->conflicts),
                'elapsed_ms' => round($elapsed * 1000, 2),
            ]);
            
            // Add timing metadata
            return $result->withAdditionalDiagnostics([
                'extraction_time_ms' => round($elapsed * 1000, 2),
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('Extraction failed', [
                'extractor' => $this->getLabel(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty result with error diagnostic
            return new ExtractionResult(
                diagnostics: ['error' => $e->getMessage()],
                warnings: ['Extraction failed: ' . $e->getMessage()],
            );
        }
    }
    
    /**
     * Default priority - override in subclass if needed.
     */
    public function getPriority(): int
    {
        return static::DEFAULT_PRIORITY;
    }
    
    // ========================================================================
    // Helper Methods for Subclasses
    // ========================================================================
    
    /**
     * Normalize headers for consistent matching.
     * 
     * @return array<string, string> Map of normalized name to original name
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $key = $this->normalizeHeaderName($header);
            $normalized[$key] = $header;
        }
        return $normalized;
    }
    
    /**
     * Normalize a single header name.
     */
    protected function normalizeHeaderName(string $header): string
    {
        // Lowercase, replace separators with underscores, trim
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[\s\-\.]+/', '_', $normalized);
        return $normalized;
    }
    
    /**
     * Check if any of the target headers exist.
     */
    protected function hasAnyHeader(array $normalizedHeaders, array $targets): bool
    {
        foreach ($targets as $target) {
            if ($this->hasHeader($normalizedHeaders, $target)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if a specific header exists (with fuzzy matching).
     */
    protected function hasHeader(array $normalizedHeaders, string $target): bool
    {
        $normalizedTarget = $this->normalizeHeaderName($target);
        
        // Exact match
        if (isset($normalizedHeaders[$normalizedTarget])) {
            return true;
        }
        
        // Partial match (target contained in header or vice versa)
        foreach (array_keys($normalizedHeaders) as $headerKey) {
            if (str_contains($headerKey, $normalizedTarget) || str_contains($normalizedTarget, $headerKey)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Find the column that maps to a specific field.
     * 
     * @return ?string Column name, or null if not mapped
     */
    protected function findColumnForField(string $field, array $fieldMappings): ?string
    {
        foreach ($fieldMappings as $column => $config) {
            $targetField = is_array($config) 
                ? ($config['target_field'] ?? $config['field'] ?? null) 
                : $config;
            
            if ($targetField === $field) {
                return $column;
            }
        }
        return null;
    }
    
    /**
     * Find the column that matches any of the target names.
     * 
     * @param array<string> $targets Possible column names
     * @param array $headers Original headers
     * @return ?string Matching column name
     */
    protected function findColumn(array $targets, array $headers): ?string
    {
        $normalized = $this->normalizeHeaders($headers);
        
        foreach ($targets as $target) {
            $normalizedTarget = $this->normalizeHeaderName($target);
            
            if (isset($normalized[$normalizedTarget])) {
                return $normalized[$normalizedTarget];
            }
            
            // Partial match
            foreach ($normalized as $key => $originalHeader) {
                if (str_contains($key, $normalizedTarget)) {
                    return $originalHeader;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract a value from a row, with optional transformation.
     */
    protected function getValue(array $row, ?string $column, mixed $default = null): mixed
    {
        if ($column === null || !isset($row[$column])) {
            return $default;
        }
        
        $value = $row[$column];
        
        // Trim strings
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Extract a numeric value from a row.
     */
    protected function getNumericValue(array $row, ?string $column, ?float $default = null): ?float
    {
        $value = $this->getValue($row, $column);
        
        if ($value === null) {
            return $default;
        }
        
        // Strip currency symbols and formatting
        if (is_string($value)) {
            $value = preg_replace('/[^0-9.\-]/', '', $value);
        }
        
        return is_numeric($value) ? (float) $value : $default;
    }
    
    /**
     * Normalize a value for use as a deduplication key.
     */
    protected function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }
    
    /**
     * Generate a slug-style key from a value.
     */
    protected function generateSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
    
    /**
     * Additional scoring based on data content (override in subclass).
     */
    protected function scoreFromData(array $normalizedHeaders, array $sampleRows): float
    {
        return 0.0;
    }
    
    /**
     * Create a ServiceResponse for successful entity creation.
     */
    protected function successResponse(array $entityCounts, string $message = null): ServiceResponse
    {
        return ServiceResponse::success(
            data: ['entity_counts' => $entityCounts],
            message: $message ?? 'Entities created successfully'
        );
    }
    
    /**
     * Create a ServiceResponse for failed entity creation.
     */
    protected function failureResponse(array $errors, array $entityCounts = []): ServiceResponse
    {
        return ServiceResponse::failure(
            errors: $errors,
            data: ['entity_counts' => $entityCounts],
            message: 'Entity creation failed'
        );
    }
}
