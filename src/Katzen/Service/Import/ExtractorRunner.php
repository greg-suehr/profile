<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Import\Extractor\AbstractDataExtractor;
use App\Katzen\Service\Response\ServiceResponse;
use Psr\Log\LoggerInterface;

/**
 * Extractor Runner - Orchestrates multiple DataExtractors
 * 
 * This service manages all registered extractors and runs them in the
 * correct order based on priority and dependencies. It:
 * 
 * 1. Collects all extractors (via service tagging or manual registration)
 * 2. Runs detection to determine which extractors should process the data
 * 3. Orders extractors by priority (higher = earlier)
 * 4. Runs extraction and collects results
 * 5. Coordinates entity creation with EntityMap for FK resolution
 * 
 * The extraction order matters because some entities depend on others:
 * - StockLocations must exist before Orders can reference them
 * - Sellables must exist before OrderItems can reference them
 * - Sellables must exist before SellableVariants can be created
 * 
 * @example Usage in DataImportService:
 * ```php
 * $relevantExtractors = $this->extractorRunner->detectRelevant($headers, $sampleRows);
 * $extractionResults = $this->extractorRunner->runAll($rows, $headers, $mapping);
 * $entityMap = $this->extractorRunner->createEntities($extractionResults, $batch);
 * ```
 */
final class ExtractorRunner
{
  /**
   * Minimum confidence score for an extractor to be considered relevant.
   */
  private const MIN_CONFIDENCE = 0.4;
  
  /**
   * @var DataExtractor[]
   */
  private array $extractors = [];
  
  public function __construct(
    private LoggerInterface $logger,
  ) {}
    
  /**
   * Register an extractor.
   * 
   * In Symfony, this would typically be done via service tagging:
   * ```yaml
   * services:
   *   App\Katzen\Service\Import\Extractor\CatalogExtractor:
   *     tags: ['katzen.import.extractor']
   * ```
   */
  public function addExtractor(DataExtractor $extractor): void
  {
    $this->extractors[] = $extractor;
  }
    
  /**
   * Get all registered extractors.
   * 
   * @return DataExtractor[]
   */
  public function getExtractors(): array
  {
    return $this->extractors;
  }
    
  /**
   * Detect which extractors are relevant for the given data.
   * 
   * Returns extractors sorted by detection confidence (highest first).
   * 
   * @param array $headers CSV column headers
   * @param array $sampleRows Sample data for pattern detection
   * @param float $minConfidence Minimum confidence threshold (default: 0.4)
   * @return array<array{extractor: DataExtractor, confidence: float, entity_types: array}>
   */
  public function detectRelevant(
    array $headers,
    array $sampleRows = [],
    float $minConfidence = self::MIN_CONFIDENCE
  ): array {
    $relevant = [];
        
    foreach ($this->extractors as $extractor) {
      $confidence = $extractor->detect($headers, $sampleRows);
      
      $this->logger->debug('Extractor detection', [
        'extractor' => $extractor->getLabel(),
        'confidence' => $confidence,
        'threshold' => $minConfidence,
        'relevant' => $confidence >= $minConfidence,
      ]);
      
      if ($confidence >= $minConfidence) {
        $relevant[] = [
          'extractor' => $extractor,
          'confidence' => $confidence,
          'entity_types' => $extractor->getEntityTypes(),
          'priority' => $extractor->getPriority(),
          'label' => $extractor->getLabel(),
        ];
      }
    }
    
    usort($relevant, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
    
    return $relevant;
  }
    
  /**
   * Run all relevant extractors and collect results.
   * 
   * Extractors are run in priority order (higher priority first).
   * Results are collected into a merged ExtractionResult.
   * 
   * @param array $rows All data rows
   * @param array $headers Column headers
   * @param ImportMapping $mapping Field mapping configuration
   * @param array|null $enabledEntityTypes Only run extractors for these types (null = all)
   * @return ExtractionResult Merged results from all extractors
   */
  public function runAll(
    array $rows,
    array $headers,
    ImportMapping $mapping,
      ?array $enabledEntityTypes = null
  ): ExtractionResult {
    $relevant = $this->detectRelevant($headers, array_slice($rows, 0, 100));
        
    if ($enabledEntityTypes !== null) {
      $relevant = array_filter($relevant, function ($item) use ($enabledEntityTypes) {
                $extractorTypes = $item['entity_types'];
                return !empty(array_intersect($extractorTypes, $enabledEntityTypes));
            });
    }
    
    usort($relevant, fn($a, $b) => $b['priority'] <=> $a['priority']);
    
    $this->logger->info('Running extractors', [
      'total_registered' => count($this->extractors),
      'relevant' => count($relevant),
      'order' => array_map(fn($r) => $r['label'], $relevant),
    ]);
    
    $mergedResult = ExtractionResult::empty();
    $extractorResults = [];
    
    foreach ($relevant as $item) {
      $extractor = $item['extractor'];
      
      $this->logger->info('Running extractor', [
        'extractor' => $extractor->getLabel(),
        'priority' => $item['priority'],
        'confidence' => $item['confidence'],
      ]);
      
      try {
        $result = $extractor->extract($rows, $headers, $mapping);
        
        $extractorResults[$extractor->getLabel()] = [
          'result' => $result,
          'record_count' => $result->getTotalRecordCount(),
          'warnings' => count($result->warnings),
          'entity_types' => $extractor->getEntityTypes(),
        ];
        
        $mergedResult = $mergedResult->merge($result);
        
        $this->logger->info('Extractor complete', [
          'extractor' => $extractor->getLabel(),
          'records' => $result->getTotalRecordCount(),
          'warnings' => count($result->warnings),
        ]);
        
      } catch (\Throwable $e) {
        $this->logger->error('Extractor failed', [
          'extractor' => $extractor->getLabel(),
          'error' => $e->getMessage(),
        ]);
        
        $extractorResults[$extractor->getLabel()] = [
          'error' => $e->getMessage(),
          'record_count' => 0,
        ];
        
      }
    }
    
    return $mergedResult->withAdditionalDiagnostics([
      'extractor_count' => count($relevant),
      'extractor_results' => $extractorResults,
    ]);
  }
    
  /**
   * Run extraction for a specific extractor by entity type.
   * 
   * @param string $entityType Entity type to extract (e.g., 'sellable')
   * @param array $rows All data rows
   * @param array $headers Column headers
   * @param ImportMapping $mapping Field mapping configuration
   * @return ExtractionResult|null Result or null if no extractor handles this type
   */
  public function runForEntityType(
    string $entityType,
    array $rows,
    array $headers,
    ImportMapping $mapping
  ): ?ExtractionResult {
    foreach ($this->extractors as $extractor) {
      if (in_array($entityType, $extractor->getEntityTypes(), true)) {
        $confidence = $extractor->detect($headers, array_slice($rows, 0, 100));
        
        if ($confidence >= self::MIN_CONFIDENCE) {
          return $extractor->extract($rows, $headers, $mapping);
            }
      }
    }
        
    return null;
  }
  
  /**
   * Create entities from extraction results.
   * 
   * Runs each extractor's createEntities() method in priority order
   * and builds an EntityMap for FK resolution.
   * 
   * @param array<string, ExtractionResult> $extractionResults Keyed by extractor label
   * @param ImportBatch $batch Import batch for tracking
   * @return ServiceResponse Success with EntityMap; failure on errors
   */
  public function createAllEntities(
    ExtractionResult $extractionResult,
    ImportBatch $batch
  ): ServiceResponse {
    $sortedExtractors = $this->extractors;
    usort($sortedExtractors, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    
    $entityMap = new EntityMap();
    $totalCounts = [];
    $errors = [];
    
    foreach ($sortedExtractors as $extractor) {
      $entityTypes = $extractor->getEntityTypes();
      
      $hasRecords = false;
      foreach ($entityTypes as $type) {
        $pluralType = $this->pluralize($type);
        if ($extractionResult->getRecordCount($pluralType) > 0 ||
            $extractionResult->getRecordCount($type) > 0) {
          $hasRecords = true;
          break;
        }
      }
      
      if (!$hasRecords) {
        continue;
      }
      
      $this->logger->info('Creating entities', [
        'extractor' => $extractor->getLabel(),
        'entity_types' => $entityTypes,
      ]);
      
      try {
        $records = [];
        foreach ($entityTypes as $type) {
          $records[$type] = $extractionResult->getRecordsForType($type);
          $records[$this->pluralize($type)] = $extractionResult->getRecordsForType($this->pluralize($type));
        }
        
        $result = $extractor->createEntities($records, $batch);
        
        if ($result->isFailure()) {
          $errors = array_merge($errors, $result->errors);
          $this->logger->warning('Entity creation had errors', [
            'extractor' => $extractor->getLabel(),
            'errors' => $result->errors,
          ]);
        }
        
        if (isset($result->data['entity_counts'])) {
          foreach ($result->data['entity_counts'] as $type => $count) {
            $totalCounts[$type] = ($totalCounts[$type] ?? 0) + $count;
          }
        }
        
        if (isset($result->data['entity_map']) && $result->data['entity_map'] instanceof EntityMap) {
          $entityMap->merge($result->data['entity_map']);
        }
        
      } catch (\Throwable $e) {
        $errors[] = sprintf(
          '%s creation failed: %s',
          $extractor->getLabel(),
          $e->getMessage()
                );
        
        $this->logger->error('Entity creation failed', [
          'extractor' => $extractor->getLabel(),
          'error' => $e->getMessage(),
        ]);
      }
    }
    
    if (!empty($errors)) {
      return ServiceResponse::failure(
        errors: $errors,
        data: [
          'entity_counts' => $totalCounts,
          'entity_map' => $entityMap,
        ],
        message: 'Entity creation completed with errors'
      );
    }
    
    return ServiceResponse::success(
      data: [
        'entity_counts' => $totalCounts,
        'entity_map' => $entityMap,
      ],
      message: 'All entities created successfully'
    );
  }
    
  /**
   * Get extraction order recommendation based on entity dependencies.
   * 
   * @param array $detectedEntityTypes Entity types detected in the data
   * @return array Ordered list of entity types for extraction
   */
  public function getExtractionOrder(array $detectedEntityTypes): array
  {
    $dependencyOrder = [
      'stock_location' => 1,  // No dependencies
      'customer' => 1,        // No dependencies
      'vendor' => 1,          // No dependencies
      'item' => 2,            // May reference location
      'sellable' => 3,        // May reference item
      'sellable_variant' => 4, // References sellable
      'order' => 5,           // References customer, location
      'order_item' => 6,      // References order, sellable
      'purchase' => 5,        // References vendor, location
      'purchase_item' => 6,   // References purchase, item
    ];
        
    $filtered = array_filter(
      $detectedEntityTypes,
      fn($type) => isset($dependencyOrder[$type])
      );
    
    usort($filtered, fn($a, $b) => 
          ($dependencyOrder[$a] ?? 99) <=> ($dependencyOrder[$b] ?? 99)
        );
    
    return $filtered;
  }
    
  /**
   * Get a summary of all registered extractors.
   */
  public function getSummary(): array
  {
    return array_map(function ($extractor) {
            return [
              'label' => $extractor->getLabel(),
              'entity_types' => $extractor->getEntityTypes(),
              'priority' => $extractor->getPriority(),
            ];
        }, $this->extractors);
  }
  
  /**
   * A helpful helper for pluralizing entity names.
   */
  private function pluralize(string $type): string
  {
    return match ($type) {
      'sellable_variant' => 'variants',
      default => $type . 's',
    };
  }
}
