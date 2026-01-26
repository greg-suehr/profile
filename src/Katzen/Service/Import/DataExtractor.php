<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Response\ServiceResponse;

/**
 * DataExtractor Interface
 * 
 * Contract for pluggable data extractors that can identify and extract
 * master data entities from import rows. Each extractor handles one
 * aggregate entity domain (Catalog, Location, Customer, Supply, etc.)
 * 
 * The extraction process follows three phases:
 * 1. detect() - Analyze headers/sample data to determine relevance (0.0-1.0 score)
 * 2. extract() - Process rows to identify unique entities and their relationships
 * 3. createEntities() - Persist extracted records as domain entities
 * 
 * This architecture enables:
 * - Automatic extractor selection based on data characteristics
 * - Composable extraction (multiple extractors can run on same dataset)
 * - Transparent diagnostics for user visibility
 * - Pluggable extension without modifying orchestrator
 */
interface DataExtractor
{
    /**
     * Detect relevance of this extractor for the given data.
     * 
     * Analyzes column headers and sample data to determine if this
     * extractor should process the dataset. Uses heuristics like:
     * - Header name patterns (product_name, sku, category)
     * - Data characteristics (numeric ranges, text patterns)
     * - Column relationships
     * 
     * @param array $headers Column names from the import file
     * @param array $sampleRows First N rows for pattern analysis (typically 10-100)
     * @return float Score between 0.0 (not relevant) and 1.0 (highly relevant)
     *               - 0.0: Extractor should not run
     *               - 0.1-0.3: Possible match, low confidence
     *               - 0.4-0.6: Likely match, medium confidence  
     *               - 0.7-0.9: Strong match, high confidence
     *               - 1.0: Perfect match (all required signals present)
     */
    public function detect(array $headers, array $sampleRows): float;
    
    /**
     * Extract unique records from the dataset.
     * 
     * Processes all rows to identify unique master data entities,
     * deduplicate by natural keys, and capture relationships.
     * Does NOT create database entities - that happens in createEntities().
     * 
     * @param array $rows All data rows to process
     * @param array $headers Column names for field lookup
     * @param ImportMapping $mapping Field mapping configuration
     * @return ExtractionResult Contains extracted records, diagnostics, and any conflicts
     */
    public function extract(array $rows, array $headers, ImportMapping $mapping): ExtractionResult;
    
    /**
     * Create domain entities from extracted records.
     * 
     * Uses find-or-create pattern to persist extracted records:
     * - Check if entity exists by natural key
     * - Create new entity if not found
     * - Track which entities were created vs found
     * 
     * @param array $extractedRecords Records from extract() keyed by natural identifier
     * @param ImportBatch $batch The import batch for tracking/rollback
     * @return ServiceResponse Success with entity counts; failure on errors
     */
    public function createEntities(array $extractedRecords, ImportBatch $batch): ServiceResponse;
    
    /**
     * Get a human-readable label for this extractor.
     * 
     * Used in UI diagnostics panels and logging to identify
     * which extractor produced which results.
     * 
     * @return string Display label (e.g., "Product Catalog (Items & Variants)")
     */
    public function getLabel(): string;
    
    /**
     * Get the entity types this extractor produces.
     * 
     * Returns array of entity type identifiers that this extractor
     * can create. Used for dependency ordering and conflict detection.
     * 
     * @return array<string> Entity type identifiers (e.g., ['item', 'sellable', 'sellable_variant'])
     */
    public function getEntityTypes(): array;
    
    /**
     * Get priority for execution ordering.
     * 
     * Higher priority extractors run first. This matters when
     * one extractor's entities are dependencies for another.
     * 
     * @return int Priority value (default 100, higher = earlier)
     */
    public function getPriority(): int;
}
