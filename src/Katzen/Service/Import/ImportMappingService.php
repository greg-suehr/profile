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
  
  /**
   * Auto-detect mapping from CSV headers and sample data
   * 
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
      
      $entityType = $entityDetection->getData()['entity_type'];
      $entityConfidence = $entityDetection->getData()['confidence'];
      
      $this->logger->info('Entity type detected', [
        'entity_type' => $entityType,
        'confidence' => $entityConfidence,
      ]);
      
      $columnMappings = [];
      $transformations = [];
      $warnings = [];
      
      foreach ($headers as $index => $header) {
        $columnData = array_column($sampleRows, $index);
        
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
      if (str_contains($mapping['target_field'], 'date') && 
          !str_contains($header, 'time')) {
        $dateColumns[$header] = $mapping;
      }
      if (str_contains($header, 'time') || str_contains($mapping['target_field'], 'time')) {
        $timeColumns[$header] = $mapping;
      }
    }
    
    if (!empty($dateColumns) && !empty($timeColumns)) {
      foreach ($dateColumns as $dateHeader => $dateMapping) {
        foreach ($timeColumns as $timeHeader => $timeMapping) {
          $compositeField = $dateMapping['target_field'];
          
          $composites[$dateHeader . '+' . $timeHeader] = [
            'target_field' => $compositeField,
            'confidence' => min($dateMapping['confidence'], $timeMapping['confidence']),
            'signals' => [
              'composite' => [
                'type' => 'datetime_combination',
                'date_column' => $dateHeader,
                'time_column' => $timeHeader,
              ]
            ],
          ];
          
          $transformations[$compositeField] = [
            'type' => 'combine_datetime',
            'date_column' => $dateHeader,
            'time_column' => $timeHeader,
            'output_format' => 'Y-m-d H:i:s',
          ];
          
          $this->logger->info('Detected composite datetime mapping', [
            'date' => $dateHeader,
            'time' => $timeHeader,
            'target' => $compositeField,
          ]);
        }
      }
    }
    
    # TODO: Look for quantity + unit pairs
    # TODO: Look for price + currency pairs
    # TODO: etc :)
    
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
    $mappedFields = array_column($columnMappings, 'target_field');
    
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
        'customer' => [
          'description' => 'Customer information',
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
        'unit_price' => [
          'description' => 'Price per unit',
          'has_default' => false,
        ],
      ],
      'item' => [
        'name' => [
          'description' => 'Item name',
          'has_default' => false,
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
    $highConfidence = array_filter($columnMappings, fn($m) => $m['confidence'] >= self::HIGH_CONFIDENCE_THRESHOLD);
    $lowConfidence = array_filter($columnMappings, fn($m) => $m['confidence'] < self::MIN_CONFIDENCE_THRESHOLD);
    
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
      $formatted[$header] = $mapping['target_field'];
    }
    
    return $formatted;
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
    
    $columnConfidences = array_column($factors['column_mappings'], 'confidence');
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
      ucfirst($entityType),
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
        'string' => ['notes'],
      ],
      # TODO: extend ImportMappingService with ... more entity types
    ];
    
    return $typeMap[$entityType][$dataType] ?? [];
  }

  public function createTemporaryMapping($entityType, $fieldMappings): ImportMapping {
    $mapping = new ImportMapping();
    $mapping->setName("temporary");
    $mapping->setEntityType($entityType);
    $mapping->setFieldMappings($fieldMappings);
    return $mapping;
  }
}
