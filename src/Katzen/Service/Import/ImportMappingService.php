<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Entity\Import\ImportMappingLearning;
use App\Katzen\Repository\Import\ImportMappingRepository;
use App\Katzen\Repository\Import\ImportMappingLearningRepository;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Import Mapping Service
 * 
 * Analyzes CSV data using multiple signals to auto-detect optimal field mappings.
 * Learns from user corrections to improve future suggestions.
 * 
 * Detection Strategy:
 * 1. Header name similarity (Levenshtein, n-gram matching)
 * 2. Data type inference (numeric, date, text patterns)
 * 3. Value distribution analysis (ranges, uniqueness, nullability)
 * 4. Relationship detection (foreign key patterns, grouping)
 * 5. Historical learning (user corrections, common patterns)
 * 
 * Multi-Entity Detection:
 * For denormalized data (e.g., POS exports with orders + line items + products),
 * the service can detect ALL extractable entity types and provide:
 * - Per-entity field mappings
 * - Header ownership assignments
 * - Extraction strategy (grouping keys, processing order)
 */
final class ImportMappingService
{
  private const MIN_CONFIDENCE_THRESHOLD = 0.6;
  private const HIGH_CONFIDENCE_THRESHOLD = 0.85;
  
  private const SIGNAL_WEIGHTS = [
    'header_similarity' => 0.35,
    'data_type_match' => 0.25,
    'pattern_match' => 0.20,
    'value_distribution' => 0.10,
    'learned_pattern' => 0.10,
  ];
  
  public function __construct(
    private EntityManagerInterface $em,
    private ImportMappingRepository $mappingRepo,
    private ImportMappingLearningRepository $learningRepo,
    private ColumnAnalyzer $columnAnalyzer,
    private EntityTypeDetector $entityTypeDetector,
    private HeaderMatcher $headerMatcher,
    private PatternRecognizer $patternRecognizer,
    private LoggerInterface $logger,
  ) {}
  
  // ========================================================================
  // Multi-Entity Detection (NEW)
  // ========================================================================
  
  /**
   * Detect all extractable entities from CSV headers and sample data
   * 
   * This is the primary entry point for the multi-entity mapping UI.
   * Returns a structured result with all detected entities, their mappings,
   * and extraction strategy.
   * 
   * @param array $headers Column headers from CSV
   * @param array $sampleRows First N rows for pattern analysis
   * @param array $options Detection options (e.g., force_entity_types)
   * @return ServiceResponse containing MultiEntityMappingResult on success
   */
  public function detectMultiEntityMapping(
    array $headers,
    array $sampleRows = [],
    array $options = []
  ): ServiceResponse {
    $this->logger->info('Starting multi-entity mapping detection', [
      'header_count' => count($headers),
      'sample_size' => count($sampleRows),
    ]);
    
    try {
      // Step 1: Detect all extractable entity types
      $entityDetection = $this->entityTypeDetector->detect($headers, $sampleRows);
      
      if (!$entityDetection->isSuccess()) {
        return ServiceResponse::failure(
          errors: ['Could not determine data type'],
          message: 'Unable to detect what kind of data this is',
          metadata: [
            'headers' => $headers,
            'suggestions' => 'Try using more descriptive column names or providing sample data'
          ]
        );
      }
      
      $detectionData = $entityDetection->getData();
      $primaryEntity = $detectionData['primary_entity'];
      $extractableEntities = $detectionData['extractable_entities'];
      $extractionStrategy = $detectionData['extraction_strategy'];
      $headerEntityMap = $detectionData['header_entity_map'];
      $allScores = $detectionData['all_scores'];
      
      $this->logger->info('Entity types detected', [
        'primary' => $primaryEntity,
        'extractable' => array_keys($extractableEntities),
        'strategy' => $extractionStrategy['type'] ?? 'unknown',
      ]);
      
      // Step 2: Generate per-entity field mappings
      $entityMappings = [];
      $allWarnings = [];
      
      foreach ($extractableEntities as $entityType => $entityData) {
        $entityHeaders = $this->getHeadersForEntity($entityType, $headerEntityMap, $headers);
        
        $mappingResult = $this->generateEntityMapping(
          $entityType,
          $entityHeaders,
          $headers,
          $sampleRows
        );
        
        $entityMappings[$entityType] = $mappingResult['mapping'];
        $allWarnings = array_merge($allWarnings, $mappingResult['warnings']);
      }
      
      // Step 3: Calculate overall confidence
      $overallConfidence = $this->calculateMultiEntityConfidence(
        $extractableEntities,
        $entityMappings,
        $extractionStrategy
      );
      
      // Step 4: Generate explanation
      $explanation = $this->generateMultiEntityExplanation(
        $primaryEntity,
        $extractableEntities,
        $extractionStrategy,
        $overallConfidence
      );
      
      // Step 5: Build result object
      $result = new MultiEntityMappingResult(
        primaryEntity: $primaryEntity,
        extractableEntities: $extractableEntities,
        extractionStrategy: $extractionStrategy,
        headerEntityMap: $headerEntityMap,
        entityMappings: $entityMappings,
        allScores: $allScores,
        overallConfidence: $overallConfidence,
        warnings: $allWarnings,
        explanation: $explanation,
      );
      
      $this->logger->info('Multi-entity detection complete', [
        'summary' => $result->getSummary(),
      ]);
      
      return ServiceResponse::success(
        data: ['detection_result' => $result],
        message: $this->getMultiEntityConfidenceMessage($result)
      );
      
    } catch (\Throwable $e) {
      $this->logger->error('Multi-entity mapping detection failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to detect mapping'
      );
    }
  }

  /**
   * Get headers that belong to a specific entity type
   */
  private function getHeadersForEntity(
    string $entityType,
    array $headerEntityMap,
    array $allHeaders
  ): array {
    $entityHeaders = [];
    
    foreach ($headerEntityMap as $header => $info) {
      // Include if primary entity matches
      if ($info['primary_entity'] === $entityType) {
        $entityHeaders[] = $header;
        continue;
      }
      
      // Also include if it's a strong match for this entity (shared headers)
      $entityMatches = $info['entity_matches'] ?? [];
      if (isset($entityMatches[$entityType]) && $entityMatches[$entityType]['score'] >= 0.5) {
        $entityHeaders[] = $header;
      }
    }
    
    return $entityHeaders;
  }

  /**
   * Generate field mapping for a specific entity type
   */
  private function generateEntityMapping(
    string $entityType,
    array $entityHeaders,
    array $allHeaders,
    array $sampleRows
  ): array {
    $columnMappings = [];
    $warnings = [];
    
    foreach ($entityHeaders as $header) {
      $columnData = array_column($sampleRows, $header);
      $analysis = $this->analyzeColumn($header, $columnData, $entityType);
      
      if ($analysis['mapping']) {
        $columnMappings[$header] = [
          'target_field' => $analysis['mapping'],
          'confidence' => $analysis['confidence'],
          'signals' => $analysis['signals'],
          'suggested_transformation' => $analysis['transformation'] ?? null,
        ];
        
        if ($analysis['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
          $warnings[] = [
            'entity_type' => $entityType,
            'column' => $header,
            'message' => "Low confidence mapping for '{$header}' → '{$analysis['mapping']}'",
            'confidence' => $analysis['confidence'],
          ];
        }
      }
    }
    
    // Create ImportMapping entity
    $mapping = new ImportMapping();
    $mapping->setName($this->generateMappingName($entityType, $entityHeaders));
    $mapping->setEntityType($entityType);
    $mapping->setFieldMappings($this->formatFieldMappings($columnMappings));
    
    // Detect composite mappings (date + time, etc.)
    $compositeMappings = $this->detectCompositeMappings($columnMappings, $allHeaders, $sampleRows);
    if ($compositeMappings) {
      $existingMappings = $mapping->getFieldMappings();
      foreach ($compositeMappings['mappings'] as $key => $compositeMapping) {
        $existingMappings[$key] = $compositeMapping['target_field'];
      }
      $mapping->setFieldMappings($existingMappings);
      
      if (!empty($compositeMappings['transformations'])) {
        $mapping->setTransformationRules($compositeMappings['transformations']);
      }
    }
    
    return [
      'mapping' => $mapping,
      'column_details' => $columnMappings,
      'warnings' => $warnings,
    ];
  }

  /**
   * Calculate overall confidence for multi-entity detection
   */
  private function calculateMultiEntityConfidence(
    array $extractableEntities,
    array $entityMappings,
    array $extractionStrategy
  ): float {
    $factors = [];
    
    // Factor 1: Average entity detection confidence
    $entityConfidences = array_column($extractableEntities, 'confidence');
    $factors['entity_detection'] = !empty($entityConfidences)
      ? array_sum($entityConfidences) / count($entityConfidences)
      : 0;
    
    // Factor 2: Mapping completeness across all entities
    $completenessScores = [];
    foreach ($entityMappings as $entityType => $mapping) {
      $validation = $this->validateMappingCompleteness(
        $mapping->getFieldMappings(),
        $entityType
      );
      $completenessScores[] = $validation['completeness_score'];
    }
    $factors['completeness'] = !empty($completenessScores)
      ? array_sum($completenessScores) / count($completenessScores)
      : 0;
    
    // Factor 3: Strategy clarity (do we have clear grouping?)
    $factors['strategy_clarity'] = match($extractionStrategy['type'] ?? 'unknown') {
      'single_entity' => 1.0,
      'denormalized_transaction' => $extractionStrategy['grouping_key'] ? 0.9 : 0.6,
      'denormalized_hierarchical' => $extractionStrategy['grouping_key'] ? 0.85 : 0.5,
      default => 0.5,
    };
    
    // Weighted combination
    $weights = [
      'entity_detection' => 0.4,
      'completeness' => 0.35,
      'strategy_clarity' => 0.25,
    ];
    
    $confidence = 0;
    foreach ($weights as $factor => $weight) {
      $confidence += ($factors[$factor] ?? 0) * $weight;
    }
    
    return min(1.0, max(0.0, $confidence));
  }

  /**
   * Generate human-readable explanation for multi-entity detection
   */
  private function generateMultiEntityExplanation(
    string $primaryEntity,
    array $extractableEntities,
    array $extractionStrategy,
    float $overallConfidence
  ): string {
    $parts = [];
    
    // Describe what we found
    $entityCount = count($extractableEntities);
    if ($entityCount === 1) {
      $parts[] = sprintf(
        "This appears to be **%s** data.",
        $this->formatEntityLabel($primaryEntity)
      );
    } else {
      $entityLabels = array_map(
        fn($e) => $this->formatEntityLabel($e),
        array_keys($extractableEntities)
      );
      $parts[] = sprintf(
        "This file contains **%d types of data**: %s.",
        $entityCount,
        implode(', ', $entityLabels)
      );
    }
    
    // Describe extraction strategy
    if ($extractionStrategy['requires_grouping'] ?? false) {
      $groupingKey = $extractionStrategy['grouping_key'] ?? 'unknown field';
      $parts[] = sprintf(
        "Records will be grouped by **%s** to extract related entities.",
        $groupingKey
      );
    }
    
    // Add strategy notes
    $notes = $extractionStrategy['notes'] ?? [];
    foreach (array_slice($notes, 0, 2) as $note) {
      $parts[] = $note;
    }
    
    // Confidence guidance
    if ($overallConfidence >= self::HIGH_CONFIDENCE_THRESHOLD) {
      $parts[] = "High confidence - ready to proceed with review.";
    } elseif ($overallConfidence >= self::MIN_CONFIDENCE_THRESHOLD) {
      $parts[] = "Please review the entity assignments and field mappings before proceeding.";
    } else {
      $parts[] = "Some manual configuration may be needed for accurate import.";
    }
    
    return implode(' ', $parts);
  }

  /**
   * Get confidence message for multi-entity detection
   */
  private function getMultiEntityConfidenceMessage(MultiEntityMappingResult $result): string
  {
    $entityCount = count($result->extractableEntities);
    $confidence = $result->overallConfidence;
    
    $entityPart = $entityCount === 1
      ? "1 entity type"
      : "{$entityCount} entity types";
    
    if ($confidence >= 0.9) {
      return "Detected {$entityPart} with high confidence - ready to import!";
    } elseif ($confidence >= 0.75) {
      return "Detected {$entityPart} - please review before importing";
    } elseif ($confidence >= 0.6) {
      return "Detected {$entityPart} with moderate confidence - review recommended";
    } else {
      return "Detected {$entityPart} - manual configuration may be needed";
    }
  }

  private function formatEntityLabel(string $entityType): string
  {
    return match($entityType) {
      'order' => 'Orders',
      'order_item' => 'Order Items',
      'sellable' => 'Products',
      'sellable_variant' => 'Product Variants',
      'item' => 'Inventory Items',
      'customer' => 'Customers',
      'vendor' => 'Vendors',
      'stock_location' => 'Locations',
      default => ucwords(str_replace('_', ' ', $entityType)),
    };
  }

  // ========================================================================
  // Legacy Single-Entity Detection (Preserved for backward compatibility)
  // ========================================================================
  
  /**
   * Auto-detect mapping from CSV headers and sample data
   * 
   * @deprecated Use detectMultiEntityMapping() for new code
   * @param array $headers Column headers from CSV
   * @param array $sampleRows First N rows for pattern analysis (default: 100)
   * @return ServiceResponse with detected mapping and confidence scores
   */
  public function detectMapping(
    array $headers, 
    array $sampleRows = [],
    array $options = []
  ): ServiceResponse {
    $this->logger->info('Starting intelligent mapping detection', [
      'header_count' => count($headers),
      'sample_size' => count($sampleRows),
    ]);
    
    try {
      $entityDetection = $this->entityTypeDetector->detect($headers, $sampleRows);
      
      if (!$entityDetection->isSuccess()) {
        return ServiceResponse::failure(
          errors: ['Could not determine data type'],
          message: 'Unable to detect what kind of data this is',
          metadata: [
            'headers' => $headers,
            'suggestions' => 'Try using more descriptive column names or providing sample data'
          ]
        );
      }

      $detectionResult = $entityDetection->getData();
      $entityType = $detectionResult['primary_entity'];
      $entityConfidence = $detectionResult['extractable_entities'][$entityType]['confidence'] 
        ?? $detectionResult['all_scores'][$entityType] 
        ?? 0;

      $this->logger->info('Entity type detected', [
        'entity_type' => $entityType,
        'confidence' => $entityConfidence,
        'all_extractable' => array_keys($detectionResult['extractable_entities'] ?? []),
      ]);
      
      $columnMappings = [];
      $transformations = [];
      $warnings = [];

      foreach ($headers as $index => $header) {
        $columnData = array_column($sampleRows, $header);
        $analysis = $this->analyzeColumn($header, $columnData, $entityType);
        
        if ($analysis['mapping']) {
          $columnMappings[$header] = [
            'target_field' => $analysis['mapping'],
            'confidence' => $analysis['confidence'],
            'signals' => $analysis['signals'],
            'suggested_transformation' => $analysis['transformation'] ?? null,
          ];
          
          if ($analysis['transformation']) {
            $transformations[$header] = $analysis['transformation'];
          }
          
          if ($analysis['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            $warnings[] = [
              'column' => $header,
              'message' => "Low confidence mapping for '{$header}' → '{$analysis['mapping']}'",
              'confidence' => $analysis['confidence'],
            ];
          }
        }
      }

      $compositeMappings = $this->detectCompositeMappings($columnMappings, $headers, $sampleRows);
      
      if ($compositeMappings) {
        $columnMappings = array_merge($columnMappings, $compositeMappings['mappings']);
        $transformations = array_merge($transformations, $compositeMappings['transformations']);
      }
      
      $validation = $this->validateMappingCompleteness($columnMappings, $entityType);
      
      if (!$validation['is_complete']) {
        $warnings = array_merge($warnings, $validation['missing_fields']);
      }
      
      $historicalPattern = $this->findHistoricalPattern($headers, $entityType);
      
      if ($historicalPattern) {
        $this->logger->info('Found historical pattern match', [
          'pattern_id' => $historicalPattern->getId(),
          'times_used' => $historicalPattern->getUsageCount(),
        ]);
      }
      
      $mapping = new ImportMapping();
      $mapping->setName($this->generateMappingName($entityType, $headers));
      $mapping->setEntityType($entityType);
      $mapping->setFieldMappings($this->formatFieldMappings($columnMappings));
      
      if (!empty($transformations)) {
        $mapping->setTransformationRules($transformations);
      }
      
      $overallConfidence = $this->calculateOverallConfidence([
        'entity_detection' => $entityConfidence,
        'column_mappings' => $columnMappings,
        'completeness' => $validation['completeness_score'],
        'historical_match' => $historicalPattern ? 0.9 : 0.5,
      ]);
      
      return ServiceResponse::success(
        data: [
          'mapping' => $mapping,
          'confidence' => $overallConfidence,
          'entity_type' => $entityType,
          'column_details' => $columnMappings,
          'warnings' => $warnings,
          'missing_required_fields' => $validation['missing_fields'] ?? [],
          'explanation' => $this->generateExplanation($entityType, $columnMappings, $overallConfidence),
          // NEW: Include multi-entity data for UI to optionally use
          'multi_entity_data' => [
            'extractable_entities' => $detectionResult['extractable_entities'] ?? [],
            'extraction_strategy' => $detectionResult['extraction_strategy'] ?? [],
            'header_entity_map' => $detectionResult['header_entity_map'] ?? [],
          ],
        ],
        message: $this->getConfidenceMessage($overallConfidence)
      );
      
    } catch (\Throwable $e) {
      $this->logger->error('Mapping detection failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to detect mapping'
      );
    }
  }

  // ========================================================================
  // Column Analysis Methods
  // ========================================================================

  /**
   * Analyze a single column to determine its mapping
   * 
   * Uses multiple signals:
   * - Header name similarity
   * - Data type patterns
   * - Value distribution
   * - Learned patterns
   */
  private function analyzeColumn(
    string $header, 
    array $columnData, 
    string $entityType
  ): array {
    $signals = [];

    $headerSignal = $this->headerMatcher->findBestMatch($header, $entityType);
    $signals['header_similarity'] = [
      'match' => $headerSignal['field'] ?? null,
      'score' => $headerSignal['score'] ?? 0,
      'method' => $headerSignal['method'] ?? 'none',
    ];
    
    $dataTypeSignal = $this->columnAnalyzer->inferDataType($columnData);
    $signals['data_type'] = [
      'type' => $dataTypeSignal['type'],
      'confidence' => $dataTypeSignal['confidence'],
      'compatible_fields' => $this->getFieldsForDataType($dataTypeSignal['type'], $entityType),
    ];

    $patternSignal = $this->patternRecognizer->detectPattern($columnData);
    $signals['pattern'] = [
      'patterns' => $patternSignal['patterns'] ?? [],
      'primary_pattern' => $patternSignal['primary'] ?? null,
    ];

    $distributionSignal = $this->columnAnalyzer->analyzeDistribution($columnData);

    $signals['distribution'] = [
      'uniqueness' => $distributionSignal['uniqueness_ratio'],
      'null_ratio' => $distributionSignal['null_ratio'],
      'range' => $distributionSignal['range'] ?? null,
      'likely_foreign_key' => $distributionSignal['uniqueness_ratio'] > 0.8,
    ];

    $learnedSignal = $this->learningRepo->findByColumnName($header, $entityType);

    if ($learnedSignal) {
      $signals['learned'] = [
        'field' => $learnedSignal->getTargetField(),
        'confidence' => min(1.0, $learnedSignal->getSuccessCount() / 10),
        'times_seen' => $learnedSignal->getSuccessCount(),
      ];
    }

    $synthesis = $this->synthesizeSignals($signals, $entityType);

    return [
      'mapping' => $synthesis['field'],
      'confidence' => $synthesis['confidence'],
      'signals' => $signals,
      'transformation' => $synthesis['transformation'] ?? null,
      'reasoning' => $synthesis['reasoning'],
    ];
  }

  /**
   * Synthesize multiple signals into a single mapping decision
   */
  private function synthesizeSignals(array $signals, string $entityType): array
  {
    $candidates = [];

    if (isset($signals['header_similarity']['match']) && $signals['header_similarity']['score'] > 0.5) {
      $field = $signals['header_similarity']['match'];
      $candidates[$field] = ($candidates[$field] ?? 0) + 
        ($signals['header_similarity']['score'] * self::SIGNAL_WEIGHTS['header_similarity']);
    }
    
    if (isset($signals['data_type']['compatible_fields'])) {
      foreach ($signals['data_type']['compatible_fields'] as $field) {
        $candidates[$field] = ($candidates[$field] ?? 0) + 
          ($signals['data_type']['confidence'] * self::SIGNAL_WEIGHTS['data_type_match']);
      }
    }
    
    if (isset($signals['pattern']['primary_pattern']['field'])) {
      $field = $signals['pattern']['primary_pattern']['field'];
      $candidates[$field] = ($candidates[$field] ?? 0) + 
        ($signals['pattern']['primary_pattern']['confidence'] * self::SIGNAL_WEIGHTS['pattern_match']);
    }

    if (isset($signals['learned']['field'])) {
      $field = $signals['learned']['field'];
      $candidates[$field] = ($candidates[$field] ?? 0) + 
        ($signals['learned']['confidence'] * self::SIGNAL_WEIGHTS['learned_pattern']);
    }
    
    if (empty($candidates)) {
      return [
        'field' => null,
        'confidence' => 0,
        'reasoning' => 'No matching signals detected',
      ];
    }
    
    arsort($candidates);
    $bestField = array_key_first($candidates);
    $confidence = $candidates[$bestField];
    
    $transformation = null;
    if (isset($signals['pattern']['primary_pattern']['transformation'])) {
      $transformation = $signals['pattern']['primary_pattern']['transformation'];
    }
    
    $reasoning = $this->explainDecision($bestField, $signals, $confidence);
    
    return [
      'field' => $bestField,
      'confidence' => min(1.0, $confidence),
      'transformation' => $transformation,
      'reasoning' => $reasoning,
    ];
  }
  
  /**
   * Detect composite mappings (e.g., separate date + time columns = datetime)
   */
  private function detectCompositeMappings(
    array $columnMappings,
    array $headers,
    array $sampleRows
  ): ?array {
    $composites = [];
    $transformations = [];
    
    $dateColumns = [];
    $timeColumns = [];
    
    foreach ($columnMappings as $header => $mapping) {
      $targetField = is_array($mapping) ? ($mapping['target_field'] ?? null) : $mapping;
      if (!$targetField) continue;
      
      if (str_contains($targetField, 'date') && !str_contains(strtolower($header), 'time')) {
        $dateColumns[$header] = $mapping;
      }
      if (str_contains(strtolower($header), 'time') || str_contains($targetField, 'time')) {
        $timeColumns[$header] = $mapping;
      }
    }
    
    if (!empty($dateColumns) && !empty($timeColumns)) {
      foreach ($dateColumns as $dateHeader => $dateMapping) {
        foreach ($timeColumns as $timeHeader => $timeMapping) {
          $targetField = is_array($dateMapping) ? $dateMapping['target_field'] : $dateMapping;
          $dateConfidence = is_array($dateMapping) ? ($dateMapping['confidence'] ?? 0.5) : 0.5;
          $timeConfidence = is_array($timeMapping) ? ($timeMapping['confidence'] ?? 0.5) : 0.5;
          
          $composites[$dateHeader . '+' . $timeHeader] = [
            'target_field' => $targetField,
            'confidence' => min($dateConfidence, $timeConfidence),
            'signals' => [
              'composite' => [
                'type' => 'datetime_combination',
                'date_column' => $dateHeader,
                'time_column' => $timeHeader,
              ]
            ],
          ];
          
          $transformations[$targetField] = [
            'type' => 'combine_datetime',
            'date_column' => $dateHeader,
            'time_column' => $timeHeader,
            'output_format' => 'Y-m-d H:i:s',
          ];
          
          $this->logger->info('Detected composite datetime mapping', [
            'date' => $dateHeader,
            'time' => $timeHeader,
            'target' => $targetField,
          ]);
        }
      }
    }
    
    if (!empty($composites)) {
      return [
        'mappings' => $composites,
        'transformations' => $transformations,
      ];
    }
    
    return null;
  }
  
  /**
   * Validate that all required fields for the entity type are mapped
   */
  private function validateMappingCompleteness(
    array $columnMappings,
    string $entityType
  ): array {
    $requiredFields = $this->getRequiredFields($entityType);
    
    // Handle both old format (target_field in array) and new format (direct value)
    $mappedFields = [];
    foreach ($columnMappings as $header => $mapping) {
      if (is_array($mapping)) {
        $mappedFields[] = $mapping['target_field'] ?? null;
      } else {
        $mappedFields[] = $mapping;
      }
    }
    $mappedFields = array_filter($mappedFields);
    
    $missingFields = [];
    foreach ($requiredFields as $field => $info) {
      if (!in_array($field, $mappedFields)) {
        $missingFields[] = [
          'field' => $field,
          'description' => $info['description'],
          'can_default' => $info['has_default'] ?? false,
        ];
      }
    }
    
    $completenessScore = empty($requiredFields) 
      ? 1.0 
      : (count($requiredFields) - count($missingFields)) / count($requiredFields);
    
    return [
      'is_complete' => empty($missingFields),
      'completeness_score' => $completenessScore,
      'missing_fields' => $missingFields,
    ];
  }

  /**
   * Get required fields for an entity type
   */
  private function getRequiredFields(string $entityType): array
  {
    return match($entityType) {
      'order' => [
        'order_number' => [
          'description' => 'Unique order identifier',
          'has_default' => true,
        ],
        'order_date' => [
          'description' => 'When the order was placed',
          'has_default' => true,
        ],
      ],
      'order_item' => [
        'order_id' => [
          'description' => 'Reference to parent order',
          'has_default' => false,
        ],
        'sellable' => [
          'description' => 'Product being sold',
          'has_default' => false,
        ],
        'quantity' => [
          'description' => 'Quantity ordered',
          'has_default' => true,
        ],
      ],
      'sellable' => [
        'name' => [
          'description' => 'Product name',
          'has_default' => false,
        ],
        'price' => [
          'description' => 'Selling price',
          'has_default' => false,
        ],
      ],
      'item' => [
        'name' => [
          'description' => 'Item name',
          'has_default' => false,
        ],
      ],
      'customer' => [
        'name' => [
          'description' => 'Customer name',
          'has_default' => false,
        ],
      ],
      'vendor' => [
        'name' => [
          'description' => 'Vendor name',
          'has_default' => false,
        ],
      ],
      'stock_location' => [
        'name' => [
          'description' => 'Location name',
          'has_default' => false,
        ],
      ],
      default => [],
    };
  }

  /**
   * Find if we've successfully used a similar pattern before
   */
  private function findHistoricalPattern(array $headers, string $entityType): ?ImportMappingLearning
  {
    $fingerprint = $this->generateHeaderFingerprint($headers);

    return $this->learningRepo->findByFingerprint($fingerprint, $entityType);
  }

  /**
   * Generate a stable fingerprint for a set of headers
   */
  private function generateHeaderFingerprint(array $headers): string
  {
    $normalized = array_map('strtolower', $headers);
    sort($normalized);
        
    return md5(implode('|', $normalized));
  }

  /**
   * Learn from user's mapping corrections
   * 
   * Called when user modifies suggested mapping
   */
  public function recordCorrection(
    string $columnName,
    string $suggestedField,
    string $actualField,
    string $entityType
  ): void {
    $learning = $this->learningRepo->findOrCreate(
      $columnName,
      $actualField,
      $entityType
    );
    
    $learning->incrementSuccessCount();
    
    if ($suggestedField !== $actualField) {
      $learning->recordFailedSuggestion($suggestedField);
    }
    
    $this->em->persist($learning);
    $this->em->flush();
    
    $this->logger->info('Recorded mapping correction', [
      'column' => $columnName,
      'suggested' => $suggestedField,
      'actual' => $actualField,
      'entity_type' => $entityType,
    ]);
  }
  
  /**
   * Generate human-readable explanation of mapping decision
   */
  private function generateExplanation(
    string $entityType,
    array $columnMappings,
    float $overallConfidence
  ): string {
    $highConfidence = array_filter($columnMappings, fn($m) => ($m['confidence'] ?? 0) >= self::HIGH_CONFIDENCE_THRESHOLD);
    $lowConfidence = array_filter($columnMappings, fn($m) => ($m['confidence'] ?? 0) < self::MIN_CONFIDENCE_THRESHOLD);
    
    $parts = [];
    
    $parts[] = "This appears to be **{$entityType}** data.";
    
    if (count($highConfidence) > 0) {
      $parts[] = sprintf(
        "I'm confident about %d field mappings.",
        count($highConfidence)
      );
    }
    
    if (count($lowConfidence) > 0) {
      $parts[] = sprintf(
        "However, %d mappings have low confidence and should be reviewed.",
        count($lowConfidence)
      );
    }
    
    if ($overallConfidence >= self::HIGH_CONFIDENCE_THRESHOLD) {
      $parts[] = "You can likely proceed with this mapping as-is.";
    } elseif ($overallConfidence >= self::MIN_CONFIDENCE_THRESHOLD) {
      $parts[] = "Please review the mappings before proceeding.";
    } else {
      $parts[] = "Manual configuration may be needed for accurate import.";
    }
    
    return implode(' ', $parts);
  }
  
  /**
   * Format column mappings for storage
   */
  private function formatFieldMappings(array $columnMappings): array
  {
    $formatted = [];
        
    foreach ($columnMappings as $header => $mapping) {
      if (is_array($mapping)) {
        $formatted[$header] = $mapping['target_field'] ?? null;
      } else {
        $formatted[$header] = $mapping;
      }
    }
    
    return array_filter($formatted);
  }

  /**
   * Calculate overall confidence from multiple factors
   */
  private function calculateOverallConfidence(array $factors): float
  {
    $weights = [
      'entity_detection' => 0.3,
      'completeness' => 0.3,
      'historical_match' => 0.2,
    ];
    
    $confidence = 
      ($factors['entity_detection'] * $weights['entity_detection']) +
      ($factors['completeness'] * $weights['completeness']) +
      ($factors['historical_match'] * $weights['historical_match']);
    
    $columnConfidences = array_filter(array_map(
      fn($m) => $m['confidence'] ?? null,
      $factors['column_mappings']
    ));
    
    if (!empty($columnConfidences)) {
      $avgColumnConfidence = array_sum($columnConfidences) / count($columnConfidences);
      $confidence += $avgColumnConfidence * 0.2;
    }
    
    return min(1.0, max(0.0, $confidence));
  }

  /**
   * Get user-friendly message based on confidence level
   */
  private function getConfidenceMessage(float $confidence): string
  {
    if ($confidence >= 0.9) {
      return "High confidence mapping detected - ready to import!";
    } elseif ($confidence >= 0.75) {
      return "Good mapping detected - please review before importing";
    } elseif ($confidence >= 0.6) {
      return "Mapping detected with moderate confidence - review recommended";
    } else {
      return "Low confidence mapping - manual configuration needed";
    }
  }

  /**
   * Explain why we chose this field mapping
   */
  private function explainDecision(string $field, array $signals, float $confidence): string
  {
    $reasons = [];

    if (isset($signals['header_similarity']['score']) && $signals['header_similarity']['score'] > 0.7) {
      $reasons[] = "header name matches '{$field}'";
    }
    
    if (isset($signals['learned']['times_seen']) && $signals['learned']['times_seen'] > 0) {
      $reasons[] = "seen this pattern {$signals['learned']['times_seen']} times before";
    }
    
    if (isset($signals['pattern']['primary_pattern'])) {
      $pattern = $signals['pattern']['primary_pattern']['name'] ?? 'pattern';
      $reasons[] = "data matches {$pattern} pattern";
    }
    
    if (isset($signals['data_type']['type'])) {
      $reasons[] = "data type is {$signals['data_type']['type']}";
    }
    
    if (empty($reasons)) {
      return "Low confidence match";
    }
    
    return ucfirst(implode(', ', $reasons));
  }

  /**
   * Generate a descriptive name for the mapping
   */
  private function generateMappingName(string $entityType, array $headers): string
  {
    $timestamp = (new \DateTime())->format('Y-m-d H:i');
    $headerCount = count($headers);
    
    return sprintf(
      "%s Import (%d columns) - %s",
      ucfirst(str_replace('_', ' ', $entityType)),
      $headerCount,
      $timestamp
    );
  }

  /**
   * Get compatible entity fields for a data type
   */
  private function getFieldsForDataType(string $dataType, string $entityType): array
  {
    $typeMap = [
      'order' => [
        'integer' => ['id', 'order_number', 'customer_id'],
        'decimal' => ['total', 'subtotal', 'tax', 'discount'],
        'datetime' => ['order_date', 'created_at', 'updated_at'],
        'string' => ['status', 'notes', 'customer_name'],
      ],
      'order_item' => [
        'integer' => ['id', 'order_id', 'sellable_id'],
        'decimal' => ['quantity', 'unit_price', 'line_total'],
        'string' => ['notes', 'product_name'],
      ],
      'sellable' => [
        'integer' => ['id', 'product_id'],
        'decimal' => ['price', 'cost'],
        'string' => ['name', 'description', 'sku', 'category'],
      ],
      'item' => [
        'integer' => ['id', 'item_id'],
        'decimal' => ['cost', 'quantity'],
        'string' => ['name', 'description', 'sku', 'upc', 'category'],
      ],
      'stock_location' => [
        'integer' => ['id', 'store_id', 'location_id'],
        'string' => ['name', 'address', 'region', 'type'],
      ],
      'customer' => [
        'integer' => ['id', 'customer_id'],
        'string' => ['name', 'email', 'phone', 'address', 'city', 'state'],
      ],
      'vendor' => [
        'integer' => ['id', 'vendor_id'],
        'string' => ['name', 'email', 'phone', 'contact', 'address'],
      ],
    ];
    
    return $typeMap[$entityType][$dataType] ?? [];
  }

  /**
   * Create a temporary mapping for testing/preview
   */
  public function createTemporaryMapping(string $entityType, array $fieldMappings): ImportMapping
  {
    $mapping = new ImportMapping();
    $mapping->setName("temporary");
    $mapping->setEntityType($entityType);
    $mapping->setFieldMappings($fieldMappings);
    return $mapping;
  }
}
