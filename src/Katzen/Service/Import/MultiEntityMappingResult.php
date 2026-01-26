<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportMapping;

/**
 * Multi-Entity Mapping Result
 * 
 * Encapsulates the results of multi-entity detection from EntityTypeDetector
 * for transport to the mapping UI. Provides structured access to:
 * 
 * - All extractable entities with confidence scores
 * - Header-to-entity ownership mappings  
 * - Extraction strategy (grouping, ordering)
 * - Per-entity field mappings
 * 
 * This is the bridge between EntityTypeDetector's analysis and the UI
 * that allows users to review and configure multi-entity imports.
 * 
 * @example From controller:
 * ```php
 * $result = $mappingService->detectMultiEntityMapping($headers, $sampleRows);
 * return $this->render('import/mapping.html.twig', [
 *     'detection' => $result,
 *     'entity_cards' => $result->getEntityCards(),
 *     'header_assignments' => $result->getHeaderAssignments(),
 * ]);
 * ```
 */
final class MultiEntityMappingResult
{
  /**
   * @param string $primaryEntity The main entity type detected
   * @param array<string, array> $extractableEntities All entities above threshold with metadata
   * @param array $extractionStrategy How to extract (grouping, ordering)
   * @param array<string, array> $headerEntityMap Which entity owns each header
   * @param array<string, ImportMapping> $entityMappings Per-entity field mappings
   * @param array $allScores Raw confidence scores for all entity types
   * @param float $overallConfidence Combined confidence score
   * @param array $warnings Issues detected during analysis
   * @param string $explanation Human-readable summary
   */
  public function __construct(
    public readonly string $primaryEntity,
    public readonly array $extractableEntities,
    public readonly array $extractionStrategy,
    public readonly array $headerEntityMap,
    public readonly array $entityMappings,
    public readonly array $allScores,
    public readonly float $overallConfidence,
    public readonly array $warnings = [],
    public readonly string $explanation = '',
  ) {}

  /**
   * Get list of entity types that can be extracted.
   * 
   * @return array<string> Entity type identifiers
   */
  public function getExtractableEntityTypes(): array
  {
    return array_keys($this->extractableEntities);
  }

  /**
   * Check if a specific entity type is extractable.
   */
  public function canExtract(string $entityType): bool
  {
    return isset($this->extractableEntities[$entityType]);
  }

  /**
   * Get confidence for a specific entity type.
   */
  public function getConfidence(string $entityType): float
  {
    return $this->extractableEntities[$entityType]['confidence'] ?? 0.0;
  }

  /**
   * Check if this is a multi-entity (denormalized) dataset.
   */
  public function isMultiEntity(): bool
  {
    return count($this->extractableEntities) > 1;
  }

  /**
   * Check if grouping is required for extraction.
   */
  public function requiresGrouping(): bool
  {
    return $this->extractionStrategy['requires_grouping'] ?? false;
  }

  /**
   * Get the field to group by for denormalized data.
   */
  public function getGroupingKey(): ?string
  {
    return $this->extractionStrategy['grouping_key'] ?? null;
  }

  /**
   * Get the recommended extraction order.
   * 
   * @return array<string> Entity types in order they should be extracted
   */
  public function getExtractionOrder(): array
  {
    return $this->extractionStrategy['extraction_order'] ?? array_keys($this->extractableEntities);
  }

  /**
   * Get headers assigned to a specific entity.
   * 
   * @return array<string> Original header names
   */
  public function getHeadersForEntity(string $entityType): array
  {
    $headers = [];
    foreach ($this->headerEntityMap as $header => $info) {
      if ($info['primary_entity'] === $entityType) {
        $headers[] = $header;
      }
    }
    return $headers;
  }

  /**
   * Get headers that are shared between multiple entities.
   * 
   * @return array<string> Headers used by more than one entity
   */
  public function getSharedHeaders(): array
  {
    $shared = [];
    foreach ($this->headerEntityMap as $header => $info) {
      if ($info['shared'] ?? false) {
        $shared[] = $header;
      }
    }
    return $shared;
  }

  /**
   * Get the mapping for a specific entity type.
   */
  public function getMappingForEntity(string $entityType): ?ImportMapping
  {
    return $this->entityMappings[$entityType] ?? null;
  }

  /**
   * Build entity cards for UI display.
   * 
   * Returns structured data for rendering entity selection/configuration cards.
   * 
   * @return array<array{
   *   entity_type: string,
   *   label: string,
   *   description: string,
   *   confidence: float,
   *   confidence_level: string,
   *   is_primary: bool,
   *   has_parent: bool,
   *   parent: ?string,
   *   has_children: bool,
   *   children: array,
   *   header_count: int,
   *   headers: array,
   *   enabled: bool,
   *   badge_variant: string
   * }>
   */
  public function getEntityCards(): array
  {
    $cards = [];
        
    foreach ($this->extractableEntities as $entityType => $data) {
      $cards[] = [
        'entity_type' => $entityType,
        'label' => $this->formatEntityLabel($entityType),
        'description' => $data['explanation'] ?? '',
        'confidence' => $data['confidence'],
        'confidence_level' => $this->getConfidenceLevel($data['confidence']),
        'confidence_percent' => round($data['confidence'] * 100),
        'is_primary' => $entityType === $this->primaryEntity,
        'has_parent' => $data['has_parent'] ?? false,
        'parent' => $data['parent'] ?? null,
        'has_children' => $data['has_children'] ?? false,
        'children' => $data['children'] ?? [],
        'header_count' => count($this->getHeadersForEntity($entityType)),
        'headers' => $this->getHeadersForEntity($entityType),
        'matched_fields' => $data['matched_fields'] ?? [],
        'enabled' => true,
        'badge_variant' => $this->getEntityBadgeVariant($entityType),
      ];
    }
    
    usort($cards, function ($a, $b) {
            if ($a['is_primary'] !== $b['is_primary']) {
              return $b['is_primary'] <=> $a['is_primary'];
            }
            return $b['confidence'] <=> $a['confidence'];
        });
    
    return $cards;
  }

  /**
   * Build header assignment table for UI.
   * 
   * Shows each header with its detected entity ownership and confidence.
   * 
   * @return array<array{
   *   header: string,
   *   normalized: string,
   *   primary_entity: ?string,
   *   primary_entity_label: string,
   *   is_shared: bool,
   *   all_matches: array,
   *   badge_variant: string
   * }>
   */
  public function getHeaderAssignments(): array
  {
    $assignments = [];
        
    foreach ($this->headerEntityMap as $header => $info) {
      $assignments[] = [
        'header' => $header,
        'normalized' => $info['normalized'],
        'primary_entity' => $info['primary_entity'],
        'primary_entity_label' => $info['primary_entity'] 
          ? $this->formatEntityLabel($info['primary_entity'])
          : 'Unmapped',
        'is_shared' => $info['shared'] ?? false,
        'all_matches' => $this->formatEntityMatches($info['entity_matches'] ?? []),
        'badge_variant' => $info['primary_entity'] 
          ? $this->getEntityBadgeVariant($info['primary_entity'])
          : 'secondary',
      ];
    }
    
    return $assignments;
  }

  /**
   * Build extraction strategy summary for UI.
   */
  public function getStrategySummary(): array
  {
    $notes = $this->extractionStrategy['notes'] ?? [];
        
    return [
      'type' => $this->extractionStrategy['type'] ?? 'single_entity',
      'type_label' => $this->formatStrategyType($this->extractionStrategy['type'] ?? 'single_entity'),
      'requires_grouping' => $this->requiresGrouping(),
      'grouping_key' => $this->getGroupingKey(),
      'extraction_order' => $this->getExtractionOrder(),
      'extraction_order_labels' => array_map(
        fn($e) => $this->formatEntityLabel($e),
        $this->getExtractionOrder()
            ),
      'notes' => $notes,
      'entity_hierarchy' => $this->extractionStrategy['entity_hierarchy'] ?? [],
    ];
  }
  
  /**
   * Get per-entity mapping details for configuration UI.
   * 
   * @return array<string, array{
   *   entity_type: string,
   *   label: string,
   *   mapping: ?ImportMapping,
   *   field_mappings: array,
   *   unmapped_headers: array,
   *   completeness: float
   * }>
   */
  public function getEntityMappingDetails(): array
  {
    $details = [];
        
    foreach ($this->extractableEntities as $entityType => $data) {
      $mapping = $this->entityMappings[$entityType] ?? null;
      $fieldMappings = $mapping?->getFieldMappings() ?? [];
      $entityHeaders = $this->getHeadersForEntity($entityType);
      
      $mappedHeaders = array_keys($fieldMappings);
      $unmappedHeaders = array_diff($entityHeaders, $mappedHeaders);
      
      $completeness = count($entityHeaders) > 0
        ? count($mappedHeaders) / count($entityHeaders)
        : 0;
      
      $details[$entityType] = [
        'entity_type' => $entityType,
        'label' => $this->formatEntityLabel($entityType),
        'mapping' => $mapping,
        'field_mappings' => $fieldMappings,
        'headers' => $entityHeaders,
        'mapped_count' => count($mappedHeaders),
        'unmapped_headers' => array_values($unmappedHeaders),
        'completeness' => $completeness,
        'completeness_percent' => round($completeness * 100),
      ];
    }
    
    return $details;
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
      'purchase' => 'Purchase Orders',
      'purchase_item' => 'Purchase Items',
      'vendor_invoice' => 'Vendor Invoices',
      default => ucwords(str_replace('_', ' ', $entityType)),
    };
  }

  private function getConfidenceLevel(float $confidence): string
  {
    if ($confidence >= 0.8) return 'high';
    if ($confidence >= 0.6) return 'medium';
    if ($confidence >= 0.4) return 'low';
    return 'very_low';
  }

  private function getEntityBadgeVariant(string $entityType): string
  {
    return match($entityType) {
      'order' => 'primary',
      'order_item' => 'primary',
      'sellable' => 'success',
      'sellable_variant' => 'success',
      'item' => 'info',
      'customer' => 'warning',
      'vendor' => 'secondary',
      'stock_location' => 'dark',
      'purchase' => 'info',
      'purchase_item' => 'info',
      default => 'secondary',
    };
  }

  private function formatStrategyType(string $type): string
  {
    return match($type) {
      'single_entity' => 'Single Entity Import',
      'denormalized_hierarchical' => 'Hierarchical Data (Parent/Child)',
      'denormalized_transaction' => 'Transaction Data (Orders + Line Items)',
      default => ucwords(str_replace('_', ' ', $type)),
    };
  }

  private function formatEntityMatches(array $matches): array
  {
    $formatted = [];
    foreach ($matches as $entityType => $matchInfo) {
      $formatted[] = [
        'entity_type' => $entityType,
        'label' => $this->formatEntityLabel($entityType),
                'score' => $matchInfo['score'],
        'match_type' => $matchInfo['match_type'],
        'badge_variant' => $this->getEntityBadgeVariant($entityType),
      ];
    }
    return $formatted;
  }

  /**
   * Convert to array for JSON serialization or session storage.
   */
  public function toArray(): array
  {
    $mappingsArray = [];
    foreach ($this->entityMappings as $entityType => $mapping) {
      $mappingsArray[$entityType] = [
        'name' => $mapping->getName(),
        'entity_type' => $mapping->getEntityType(),
        'field_mappings' => $mapping->getFieldMappings(),
        'transformation_rules' => $mapping->getTransformationRules(),
      ];
    }
    
    return [
      'primary_entity' => $this->primaryEntity,
      'extractable_entities' => $this->extractableEntities,
      'extraction_strategy' => $this->extractionStrategy,
      'header_entity_map' => $this->headerEntityMap,
      'entity_mappings' => $mappingsArray,
      'all_scores' => $this->allScores,
      'overall_confidence' => $this->overallConfidence,
      'warnings' => $this->warnings,
      'explanation' => $this->explanation,
    ];
  }

  /**
   * Create summary for logging/debugging.
   */
  public function getSummary(): string
  {
    $entityList = implode(', ', array_map(
      fn($e) => sprintf('%s (%.0f%%)', $e, $this->getConfidence($e) * 100),
      $this->getExtractableEntityTypes()
        ));
    
    return sprintf(
      'Multi-Entity Detection: %s | Strategy: %s | Grouping: %s | Confidence: %.0f%%',
      $entityList,
      $this->extractionStrategy['type'] ?? 'unknown',
      $this->getGroupingKey() ?? 'none',
      $this->overallConfidence * 100
    );
  }
}
