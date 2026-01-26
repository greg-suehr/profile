<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Service\Response\ServiceResponse;

/**
 * Entity Type Detector - Determines what entities can be extracted from a CSV
 * 
 * Analyzes column headers and sample data to detect entities we might extract:
 * - Order/transaction data (Order, OrderItems)
 * - Product/item data (Sellable, SellableVariant)
 * - Customer data
 * - Inventory/supply data (Purchase, PurchaseItem)
 * - Invoice/billing data (Invoice, VendorInvoice)
 * - Location data (StockLocation)
 * 
 * Supports denormalized files (like, a bunch of useless reports your expensive
 * software let's you export) containing multiple entity types that need
 * extracted and mapped for an import.
 */
final class EntityTypeDetector
{
  private const MIN_EXTRACTION_CONFIDENCE = 0.4;
  
  /**
   * Entity type signatures - column patterns that indicate specific entity types
   * 
   * Structure:
   * - required_any: Groups of columns where at least one from each group must exist
   * - strong_indicators: Columns that strongly suggest this entity type
   * - weak_indicators: Columns that mildly suggest this entity type
   * - identifying_fields: Fields that serve as unique identifiers/keys
   * - composite_patterns: Multi-column patterns that indicate the entity
   */
  private const ENTITY_SIGNATURES = [
    'order' => [
      'required_any' => [
        ['order_id', 'order_number', 'transaction_id', 'receipt_no', 'sale_id'],
      ],
      'strong_indicators' => [
        'order_date', 'transaction_date', 'sale_date', 'purchase_date',
        'customer', 'customer_id', 'customer_name',
        'order_total', 'subtotal', 'grand_total',
        'order_status', 'status',
        'payment_method', 'payment_type',
      ],
      'weak_indicators' => [
        'discount', 'shipping', 'notes', 'order_notes',
        'transaction_time', 'order_time',
        'channel', 'source', 'register',
      ],
      'identifying_fields' => ['order_id', 'order_number', 'transaction_id', 'receipt_no', 'sale_id'],
      'composite_patterns' => [
        'datetime_split' => ['date', 'time'],
      ],
    ],
    
    'order_item' => [
      'required_any' => [
        ['order_id', 'transaction_id', 'parent_id', 'receipt_no', 'sale_id'],
        ['quantity', 'qty', 'transaction_qty', 'sold_qty', 'item_qty'],
      ],
      'strong_indicators' => [
        'unit_price', 'price', 'selling_price', 'item_price',
        'line_total', 'item_total', 'extended_price',
        'product', 'item', 'sku', 'product_id', 'item_id', 'sellable_id',
        'product_name', 'item_name', 'product_detail',
      ],
      'weak_indicators' => [
        'discount', 'item_discount', 'line_discount',
        'tax_rate', 'item_tax',
        'notes', 'line_notes', 'item_notes',
        'modifier', 'modifiers', 'options',
      ],
      'identifying_fields' => ['line_id', 'order_item_id', 'line_number'],
      'composite_patterns' => [
        'line_item_pair' => ['quantity', 'price'],
        'product_reference' => ['product_id', 'product_name'],
      ],
    ],
    
    'sellable' => [
      'required_any' => [
        ['name', 'product_name', 'product_detail', 'title', 'item_name', 'sellable_name'],
      ],
      'strong_indicators' => [
        'price', 'selling_price', 'retail_price', 'unit_price', 'base_price',
        'cost', 'unit_cost',
        'sku', 'product_sku',
        'category', 'product_category', 'department',
        'description', 'product_description',
        'product_id', 'sellable_id', 'item_id',
        'product_type', 'item_type',
        'in_stock', 'available', 'active',
      ],
      'weak_indicators' => [
        'image', 'image_url', 'photo',
        'url', 'product_url',
        'brand', 'manufacturer',
        'tags', 'keywords',
        'weight', 'dimensions',
        'barcode', 'upc', 'ean',
      ],
      'identifying_fields' => ['product_id', 'sellable_id', 'sku'],
      'composite_patterns' => [
        'product_catalog' => ['name', 'price', 'category'],
      ],
    ],
    
    'sellable_variant' => [
      'required_any' => [
        ['variant', 'variant_name', 'option', 'size', 'color', 'style'],
      ],
      'strong_indicators' => [
        'variant_id', 'variant_sku',
        'parent_id', 'parent_sku', 'product_id', 'sellable_id',
        'variant_price', 'price_adjustment', 'price_modifier',
        'option_value', 'attribute_value',
      ],
      'weak_indicators' => [
        'variant_stock', 'variant_weight',
        'option_name', 'attribute_name',
      ],
      'identifying_fields' => ['variant_id', 'variant_sku'],
      'composite_patterns' => [
        'variant_definition' => ['variant_name', 'parent_id'],
      ],
    ],
    
    'item' => [
      'required_any' => [
        ['name', 'item_name', 'ingredient_name', 'material_name', 'raw_material'],
      ],
      'strong_indicators' => [
        'category', 'subcategory', 'item_category',
        'type', 'item_type',
        'sku', 'item_sku',
        'upc', 'barcode', 'ean', 'gtin',
        'description', 'item_description',
        'unit', 'unit_of_measure', 'uom', 'base_unit',
        'product_id', 'item_id', 'material_id',
        'product_type',
      ],
      'weak_indicators' => [
        'brand', 'manufacturer', 'supplier', 'vendor',
        'weight', 'dimensions', 'volume',
        'shelf_life', 'storage_temp',
        'allergens', 'dietary',
      ],
      'identifying_fields' => ['item_id', 'sku', 'upc', 'barcode'],
      'composite_patterns' => [
        'inventory_item' => ['name', 'unit', 'category'],
      ],
    ],
    
    'customer' => [
      'required_any' => [
        ['customer_name', 'full_name', 'name', 'contact_name'],
        ['email', 'customer_email'],
        ['phone', 'customer_phone', 'mobile'],
      ],
      'strong_indicators' => [
        'customer_id', 'account_number', 'account_id',
        'address', 'street', 'address_line_1',
        'city', 'state', 'province', 'zip', 'postal_code', 'country',
        'first_name', 'last_name',
        'company', 'company_name', 'business_name',
      ],
      'weak_indicators' => [
        'birth_date', 'birthday', 'dob',
        'created_date', 'signup_date', 'join_date',
        'notes', 'customer_notes',
        'tax_exempt', 'tax_id',
        'loyalty_points', 'reward_points',
      ],
      'identifying_fields' => ['customer_id', 'account_number', 'email'],
      'composite_patterns' => [
        'full_address' => ['street', 'city', 'state', 'zip'],
        'name_parts' => ['first_name', 'last_name'],
      ],
    ],
    
    'vendor' => [
      'required_any' => [
        ['vendor_name', 'supplier_name', 'company_name', 'vendor'],
      ],
      'strong_indicators' => [
        'vendor_id', 'supplier_id',
        'contact', 'contact_name', 'contact_person',
        'email', 'vendor_email',
        'phone', 'vendor_phone',
        'address', 'vendor_address',
        'payment_terms', 'terms', 'net_terms',
        'account_number', 'vendor_account',
      ],
      'weak_indicators' => [
        'tax_id', 'ein', 'vat_number',
        'website', 'url',
        'notes', 'vendor_notes',
        'lead_time', 'min_order',
      ],
      'identifying_fields' => ['vendor_id', 'supplier_id', 'account_number'],
      'composite_patterns' => [],
    ],
    
    'stock_location' => [
      'required_any' => [
        ['location', 'location_name', 'store', 'store_name', 'warehouse', 'site', 'store_id'],
      ],
      'strong_indicators' => [
        'location_id', 'store_id', 'warehouse_id', 'site_id',
        'address', 'location_address', 'store_address',
        'city', 'state', 'zip',
        'store_location', 'warehouse_location',
      ],
      'weak_indicators' => [
        'manager', 'store_manager',
        'phone', 'store_phone',
        'capacity', 'storage_capacity',
        'type', 'location_type',
        'active', 'is_active',
      ],
      'identifying_fields' => ['location_id', 'store_id', 'warehouse_id'],
      'composite_patterns' => [],
    ],
    
    'purchase' => [
      'required_any' => [
        ['po_number', 'purchase_order', 'po_id', 'purchase_id'],
      ],
      'strong_indicators' => [
        'vendor', 'vendor_id', 'supplier', 'supplier_id',
        'order_date', 'po_date', 'purchase_date',
        'expected_date', 'delivery_date', 'eta',
        'total', 'po_total', 'purchase_total',
        'status', 'po_status',
      ],
      'weak_indicators' => [
        'notes', 'po_notes',
        'ship_to', 'delivery_address',
        'terms', 'payment_terms',
      ],
      'identifying_fields' => ['po_number', 'purchase_order', 'po_id'],
      'composite_patterns' => [],
    ],
    
    'purchase_item' => [
      'required_any' => [
        ['po_number', 'purchase_order', 'po_id', 'purchase_id'],
        ['quantity', 'qty', 'order_qty', 'po_qty'],
      ],
      'strong_indicators' => [
        'item', 'item_id', 'product', 'product_id', 'sku',
        'unit_cost', 'cost', 'price',
        'line_total', 'extended_cost',
      ],
      'weak_indicators' => [
        'received_qty', 'qty_received',
        'notes', 'line_notes',
      ],
      'identifying_fields' => ['line_id', 'po_line_id'],
      'composite_patterns' => [],
    ],
    
    'vendor_invoice' => [
      'required_any' => [
        ['invoice_number', 'vendor_invoice', 'bill_number', 'ap_invoice'],
      ],
      'strong_indicators' => [
        'vendor', 'vendor_id', 'supplier',
        'invoice_date', 'bill_date',
        'due_date', 'payment_due',
        'total', 'invoice_total', 'amount_due',
        'po_number', 'purchase_order',
      ],
      'weak_indicators' => [
        'terms', 'payment_terms',
        'notes', 'memo',
        'status', 'payment_status',
      ],
      'identifying_fields' => ['invoice_number', 'vendor_invoice', 'bill_number'],
      'composite_patterns' => [],
    ],
  ];
  
  /**
   * Entity relationships - defines parent/child and sibling relationships
   * Used to understand extraction dependencies and grouping strategies
   */
  private const ENTITY_RELATIONSHIPS = [
    'order' => [
      'children' => ['order_item'],
      'references' => ['customer', 'stock_location'],
    ],
    'order_item' => [
      'parent' => 'order',
      'references' => ['sellable', 'sellable_variant'],
    ],
    'sellable' => [
      'children' => ['sellable_variant'],
      'references' => ['item'],
    ],
    'sellable_variant' => [
      'parent' => 'sellable',
    ],
    'item' => [
      'children' => ['item_variant'],
    ],
    'purchase' => [
      'children' => ['purchase_item'],
      'references' => ['vendor'],
    ],
    'purchase_item' => [
      'parent' => 'purchase',
      'references' => ['item'],
    ],
    'vendor_invoice' => [
      'children' => ['vendor_invoice_item'],
      'references' => ['vendor', 'purchase'],
    ],
  ];

  public function __construct(
    private HeaderMatcher $headerMatcher,
  ) {}
  
  /**
   * Detect all extractable entity types from headers and sample data
   * 
   * @param array $headers CSV column headers
   * @param array $sampleRows Sample data rows (optional but improves accuracy)
   * @return ServiceResponse with extractable_entities and analysis
   */
  public function detect(array $headers, array $sampleRows = []): ServiceResponse
  {
    if (empty($headers)) {
      return ServiceResponse::failure(
        errors: ['No headers provided'],
        message: 'Cannot detect entity types without column headers'
      );
    }
    
    $normalizedHeaders = array_map(
      fn($h) => $this->normalizeHeader($h),
      $headers
    );
    
    $entityScores = [];
    foreach (self::ENTITY_SIGNATURES as $entityType => $signature) {
      $scoreData = $this->scoreEntityType($normalizedHeaders, $signature, $sampleRows);
      $entityScores[$entityType] = $scoreData;
    }
    
    $extractableEntities = array_filter(
      $entityScores,
      fn($data) => $data['confidence'] >= self::MIN_EXTRACTION_CONFIDENCE
    );
    
    uasort($extractableEntities, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
    
    if (empty($extractableEntities)) {
      return ServiceResponse::failure(
        errors: ['No entity types detected with sufficient confidence'],
        message: 'Unable to identify extractable data types',
        metadata: [
          'all_scores' => array_map(fn($d) => $d['confidence'], $entityScores),
          'headers' => $headers,
          'threshold' => self::MIN_EXTRACTION_CONFIDENCE,
        ]
      );
    }
    
    $primaryEntity = array_key_first($extractableEntities);
    
    $extractionStrategy = $this->determineExtractionStrategy(
      $extractableEntities,
      $normalizedHeaders
    );
    
    $headerEntityMap = $this->mapHeadersToEntities(
      $normalizedHeaders,
      $headers,
      array_keys($extractableEntities)
    );
    
    return ServiceResponse::success(
      data: [
        'primary_entity' => $primaryEntity,
        'extractable_entities' => $this->formatExtractableEntities($extractableEntities),
        'extraction_strategy' => $extractionStrategy,
        'header_entity_map' => $headerEntityMap,
        'all_scores' => array_map(fn($d) => round($d['confidence'], 3), $entityScores),
      ],
      message: sprintf(
        "Detected %d extractable entities: %s",
        count($extractableEntities),
        implode(', ', array_keys($extractableEntities))
      )
    );
  }
  
  /**
   * Score how well headers match an entity type signature
   * Returns detailed scoring breakdown
   */
  private function scoreEntityType(
    array $normalizedHeaders,
    array $signature,
    array $sampleRows
  ): array {
    $scores = [
      'required' => 0.0,
      'strong' => 0.0,
      'weak' => 0.0,
      'composite' => 0.0,
    ];
    
    $matchedFields = [
      'required' => [],
      'strong' => [],
      'weak' => [],
    ];
    
    $requiredGroups = $signature['required_any'] ?? [];
    $requiredMatches = 0;
    
    foreach ($requiredGroups as $groupIndex => $requiredGroup) {
      $matched = $this->findMatchingHeaders($normalizedHeaders, $requiredGroup);
      if (!empty($matched)) {
        $requiredMatches++;
        $matchedFields['required'] = array_merge($matchedFields['required'], $matched);
      }
    }
    
    $scores['required'] = count($requiredGroups) > 0
      ? $requiredMatches / count($requiredGroups)
      : 0;
    
    if ($scores['required'] < 1.0 && count($requiredGroups) > 0) {
      return [
        'confidence' => 0,
        'scores' => $scores,
        'matched_fields' => $matchedFields,
      ];
    }
    
    $strongIndicators = $signature['strong_indicators'] ?? [];
    if (!empty($strongIndicators)) {
      $matched = $this->findMatchingHeaders($normalizedHeaders, $strongIndicators);
      $matchedFields['strong'] = $matched;
      $scores['strong'] = count($matched) / count($strongIndicators);
    }
    
    $weakIndicators = $signature['weak_indicators'] ?? [];
    if (!empty($weakIndicators)) {
      $matched = $this->findMatchingHeaders($normalizedHeaders, $weakIndicators);
      $matchedFields['weak'] = $matched;
      $scores['weak'] = count($matched) / count($weakIndicators);
    }
    
    $compositePatterns = $signature['composite_patterns'] ?? [];
    if (!empty($compositePatterns)) {
      $compositeMatches = 0;
      foreach ($compositePatterns as $patternName => $requiredHeaders) {
        if ($this->hasAllHeaders($normalizedHeaders, $requiredHeaders)) {
          $compositeMatches++;
        }
      }
      $scores['composite'] = $compositeMatches / count($compositePatterns);
    }
    
    $weights = [
      'required' => 0.35,
      'strong' => 0.40,
      'weak' => 0.15,
      'composite' => 0.10,
    ];
    
    $confidence = 
      ($scores['required'] * $weights['required']) +
      ($scores['strong'] * $weights['strong']) +
      ($scores['weak'] * $weights['weak']) +
      ($scores['composite'] * $weights['composite']);
    
    return [
      'confidence' => $confidence,
      'scores' => $scores,
      'matched_fields' => $matchedFields,
    ];
  }

  /**
   * Find all headers that match any of the search terms
   */
  private function findMatchingHeaders(array $normalizedHeaders, array $searchTerms): array
  {
    $matched = [];
    foreach ($searchTerms as $term) {
      $normalizedTerm = $this->normalizeHeader($term);
      foreach ($normalizedHeaders as $header) {
        if ($this->headersMatch($header, $normalizedTerm)) {
          $matched[] = $term;
          break;
        }
      }
    }
    return array_unique($matched);
  }

  /**
   * Check if headers contain any of the specified values
   */
  private function hasAnyHeader(array $normalizedHeaders, array $searchTerms): bool
  {
    return !empty($this->findMatchingHeaders($normalizedHeaders, $searchTerms));
  }
  
  /**
   * Check if headers contain all of the specified values
   */
  private function hasAllHeaders(array $normalizedHeaders, array $searchTerms): bool
  {
    foreach ($searchTerms as $term) {
      if (!$this->hasHeader($normalizedHeaders, $term)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Check if a specific header exists (fuzzy matching)
   */
  private function hasHeader(array $normalizedHeaders, string $searchTerm): bool
  {
    $normalized = $this->normalizeHeader($searchTerm);
    foreach ($normalizedHeaders as $header) {
      if ($this->headersMatch($header, $normalized)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Check if two normalized headers match (exact, contains, or fuzzy)
   */
  private function headersMatch(string $header, string $searchTerm): bool
  {
    if ($header === $searchTerm) {
      return true;
    }
    
    if (str_contains($header, $searchTerm) || str_contains($searchTerm, $header)) {
      return true;
    }
    
    if (strlen($header) > 3 && strlen($searchTerm) > 3) {
      if (levenshtein($header, $searchTerm) <= 2) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Determine the extraction strategy based on detected entities
   */
  private function determineExtractionStrategy(
    array $extractableEntities,
    array $normalizedHeaders
  ): array {
    $entityTypes = array_keys($extractableEntities);
    
    $strategy = [
      'type' => 'single_entity',
      'requires_grouping' => false,
      'grouping_key' => null,
      'entity_hierarchy' => [],
      'extraction_order' => $entityTypes,
      'notes' => [],
    ];
    
    foreach ($entityTypes as $entityType) {
      $relationships = self::ENTITY_RELATIONSHIPS[$entityType] ?? [];
      
      $children = $relationships['children'] ?? [];
      $extractableChildren = array_intersect($children, $entityTypes);
      
      if (!empty($extractableChildren)) {
        $strategy['type'] = 'denormalized_hierarchical';
        $strategy['requires_grouping'] = true;
        $strategy['entity_hierarchy'][$entityType] = $extractableChildren;
        
        $parentSignature = self::ENTITY_SIGNATURES[$entityType] ?? [];
        $identifyingFields = $parentSignature['identifying_fields'] ?? [];
        foreach ($identifyingFields as $field) {
          if ($this->hasHeader($normalizedHeaders, $field)) {
            $strategy['grouping_key'] = $field;
            break;
          }
        }
      }
    }
    
    if (in_array('order', $entityTypes) && in_array('order_item', $entityTypes)) {
      $strategy['type'] = 'denormalized_transaction';
      $strategy['notes'][] = 'Transaction data with line items - group by transaction_id/order_id';
      
      $strategy['extraction_order'] = $this->orderByDependency($entityTypes);
    }
    
    if (in_array('sellable', $entityTypes) && in_array('item', $entityTypes)) {
      $strategy['notes'][] = 'Product catalog data - may create both Items and Sellables';
    }
    
    if (in_array('sellable', $entityTypes) && in_array('sellable_variant', $entityTypes)) {
      $strategy['notes'][] = 'Product with variants - extract Sellables first, then variants';
    }
    
    $referenceEntities = array_intersect(
      ['stock_location', 'customer', 'vendor'],
      $entityTypes
    );
    if (!empty($referenceEntities)) {
      $strategy['notes'][] = sprintf(
        'Reference entities detected (%s) - extract first for foreign key resolution',
        implode(', ', $referenceEntities)
      );
      $strategy['extraction_order'] = array_merge(
        $referenceEntities,
        array_diff($strategy['extraction_order'], $referenceEntities)
      );
    }
    
    return $strategy;
  }

  /**
   * Order entity types by dependency (parents before children)
   */
  private function orderByDependency(array $entityTypes): array
  {
    $ordered = [];
    $remaining = $entityTypes;
    $maxIterations = count($entityTypes) * 2; # Don't loop forever...
    $iteration = 0;
    
    while (!empty($remaining) && $iteration < $maxIterations) {
      $iteration++;
      foreach ($remaining as $key => $entityType) {
        $relationships = self::ENTITY_RELATIONSHIPS[$entityType] ?? [];
        $parent = $relationships['parent'] ?? null;
        
        if ($parent === null || in_array($parent, $ordered) || !in_array($parent, $entityTypes)) {
          $ordered[] = $entityType;
          unset($remaining[$key]);
        }
      }
    }
    
    return $ordered;
  }

  /**
   * Map each header to its most likely entity owner
   */
  private function mapHeadersToEntities(
    array $normalizedHeaders,
    array $originalHeaders,
    array $extractableEntityTypes
  ): array {
    $headerMap = [];
    
    foreach ($normalizedHeaders as $index => $normalizedHeader) {
      $originalHeader = $originalHeaders[$index];
      $entityMatches = [];
      
      foreach ($extractableEntityTypes as $entityType) {
        $signature = self::ENTITY_SIGNATURES[$entityType] ?? [];
        $score = 0;
        $matchType = null;
        
        $identifyingFields = $signature['identifying_fields'] ?? [];
        if ($this->headerMatchesAny($normalizedHeader, $identifyingFields)) {
          $score = 1.0;
          $matchType = 'identifying';
        }
        
        if ($score < 1.0) {
          foreach ($signature['required_any'] ?? [] as $group) {
            if ($this->headerMatchesAny($normalizedHeader, $group)) {
              $score = max($score, 0.9);
              $matchType = $matchType ?? 'required';
            }
          }
        }
        
        if ($score < 0.9) {
          if ($this->headerMatchesAny($normalizedHeader, $signature['strong_indicators'] ?? [])) {
            $score = max($score, 0.7);
            $matchType = $matchType ?? 'strong';
          }
        }
        
        if ($score < 0.7) {
          if ($this->headerMatchesAny($normalizedHeader, $signature['weak_indicators'] ?? [])) {
            $score = max($score, 0.4);
            $matchType = $matchType ?? 'weak';
          }
        }
        
        if ($score > 0) {
          $entityMatches[$entityType] = [
            'score' => $score,
            'match_type' => $matchType,
          ];
        }
      }
      
      uasort($entityMatches, fn($a, $b) => $b['score'] <=> $a['score']);
      
      $headerMap[$originalHeader] = [
        'normalized' => $normalizedHeader,
        'primary_entity' => !empty($entityMatches) ? array_key_first($entityMatches) : null,
        'entity_matches' => $entityMatches,
        'shared' => count(array_filter($entityMatches, fn($m) => $m['score'] >= 0.5)) > 1,
      ];
    }
    
    return $headerMap;
  }

  /**
   * Check if a header matches any of the given terms
   */
  private function headerMatchesAny(string $normalizedHeader, array $terms): bool
  {
    foreach ($terms as $term) {
      if ($this->headersMatch($normalizedHeader, $this->normalizeHeader($term))) {
        return true;
      }
    }
    return false;
  }

  /**
   * Format extractable entities for response
   */
  private function formatExtractableEntities(array $extractableEntities): array
  {
    $formatted = [];
    
    foreach ($extractableEntities as $entityType => $scoreData) {
      $relationships = self::ENTITY_RELATIONSHIPS[$entityType] ?? [];
      
      $formatted[$entityType] = [
        'confidence' => round($scoreData['confidence'], 3),
        'matched_fields' => $scoreData['matched_fields'],
        'scores' => array_map(fn($s) => round($s, 3), $scoreData['scores']),
        'has_parent' => isset($relationships['parent']),
        'parent' => $relationships['parent'] ?? null,
        'has_children' => !empty($relationships['children'] ?? []),
        'children' => $relationships['children'] ?? [],
        'explanation' => $this->explainDetection($entityType, $scoreData),
      ];
    }
    
    return $formatted;
  }

  /**
   * Generate explanation of why this entity type was detected
   */
  private function explainDetection(string $entityType, array $scoreData): string
  {
    $parts = ["**{$entityType}** detected:"];
    
    $matchedFields = $scoreData['matched_fields'];
    
    if (!empty($matchedFields['required'])) {
      $parts[] = sprintf(
        "- Required fields: %s",
        implode(', ', array_slice($matchedFields['required'], 0, 4))
      );
    }
    
    if (!empty($matchedFields['strong'])) {
      $parts[] = sprintf(
        "- Strong indicators: %s",
        implode(', ', array_slice($matchedFields['strong'], 0, 4))
      );
    }
    
    if (!empty($matchedFields['weak'])) {
      $parts[] = sprintf(
        "- Additional fields: %s",
        implode(', ', array_slice($matchedFields['weak'], 0, 3))
      );
    }
    
    $confidence = $scoreData['confidence'];
    if ($confidence >= 0.8) {
      $parts[] = "- Confidence: Very High";
    } elseif ($confidence >= 0.6) {
      $parts[] = "- Confidence: High";
    } elseif ($confidence >= 0.5) {
      $parts[] = "- Confidence: Moderate";
    } else {
      $parts[] = "- Confidence: Low";
    }
    
    return implode("\n", $parts);
  }
  
  /**
   * Normalize header for comparison
   */
  private function normalizeHeader(string $header): string
  {
    $normalized = strtolower($header);
    $normalized = preg_replace('/[\s\-\.]+/', '_', $normalized);
    $normalized = trim($normalized, '_');
    
    return $normalized;
  }
  
  /**
   * Get expected entity schema for UI display
   */
  public function getEntitySchema(string $entityType): array
  {
    $signature = self::ENTITY_SIGNATURES[$entityType] ?? null;
    $relationships = self::ENTITY_RELATIONSHIPS[$entityType] ?? [];
    
    if (!$signature) {
      return [
        'description' => ucfirst($entityType) . ' data',
        'required_fields' => [],
        'optional_fields' => [],
        'relationships' => [],
      ];
    }
    
    $requiredFields = [];
    foreach ($signature['required_any'] ?? [] as $group) {
      $requiredFields[] = implode(' OR ', $group);
    }
    
    return [
      'entity_type' => $entityType,
      'description' => $this->getEntityDescription($entityType),
      'required_fields' => $requiredFields,
      'strong_indicators' => $signature['strong_indicators'] ?? [],
      'optional_fields' => $signature['weak_indicators'] ?? [],
      'identifying_fields' => $signature['identifying_fields'] ?? [],
      'relationships' => [
        'parent' => $relationships['parent'] ?? null,
        'children' => $relationships['children'] ?? [],
        'references' => $relationships['references'] ?? [],
      ],
    ];
  }

  /**
   * Get human-readable description for entity type
   */
  private function getEntityDescription(string $entityType): string
  {
    return match($entityType) {
      'order' => 'Customer orders or sales transactions',
      'order_item' => 'Line items within an order',
      'sellable' => 'Products available for sale',
      'sellable_variant' => 'Product variations (size, color, etc.)',
      'item' => 'Inventory items or raw materials',
      'customer' => 'Customer contact and account information',
      'vendor' => 'Supplier or vendor information',
      'stock_location' => 'Warehouse, store, or storage locations',
      'purchase' => 'Purchase orders to vendors',
      'purchase_item' => 'Line items on a purchase order',
      'vendor_invoice' => 'Invoices received from vendors',
      default => ucfirst(str_replace('_', ' ', $entityType)) . ' data',
    };
  }

  /**
   * Get all supported entity types
   */
  public function getSupportedEntityTypes(): array
  {
    return array_keys(self::ENTITY_SIGNATURES);
  }

  /**
   * Get entity relationships for a specific type
   */
  public function getEntityRelationships(string $entityType): array
  {
    return self::ENTITY_RELATIONSHIPS[$entityType] ?? [];
  }
}
