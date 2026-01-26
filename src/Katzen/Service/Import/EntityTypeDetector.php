<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Service\Response\ServiceResponse;

/**
 * Entity Type Detector - Determines what kind of data a CSV contains
 * 
 * Analyzes column headers and sample data to detect:
 * - Order/transaction data
 * - Product/item data  
 * - Customer data
 * - Inventory data
 * - Invoice/billing data
 */
final class EntityTypeDetector
{
  /**
   * Entity type signatures - column patterns that indicate specific entity types
   */
  private const ENTITY_SIGNATURES = [
    'order' => [
      'required_any' => [
        ['order_id', 'order_number', 'transaction_id', 'receipt_no'],
        ['order_date', 'transaction_date', 'sale_date', 'purchase_date'],
      ],
      'strong_indicators' => [
        'customer', 'total', 'subtotal', 'tax', 'status', 'line_items',
      ],
      'weak_indicators' => [
        'discount', 'shipping', 'payment_method', 'notes',
      ],
      'composite_patterns' => [
        'datetime_split' => ['date', 'time'],
        'line_item_pair' => ['quantity', 'price'],
      ],
    ],
    'order_item' => [
      'required_any' => [
        ['order_id', 'transaction_id', 'parent_id'],
        ['product', 'item', 'sku', 'product_id'],
        ['quantity', 'qty', 'transaction_qty'],
      ],
      'strong_indicators' => [
        'unit_price', 'line_total', 'product_name', 'item_name',
      ],
      'weak_indicators' => [
        'discount', 'tax_rate', 'notes',
      ],
    ],
    'item' => [
      'required_any' => [
        ['name', 'item_name', 'product_name', 'title'],
      ],
      'strong_indicators' => [
        'category', 'subcategory', 'type', 'sku', 'upc', 'barcode',
        'description', 'unit', 'unit_of_measure',
      ],
      'weak_indicators' => [
        'brand', 'manufacturer', 'supplier', 'weight', 'dimensions',
      ],
    ],
    'sellable' => [
      'required_any' => [
        ['name', 'product_name', 'product_detail', 'title'],
        ['price', 'selling_price', 'retail_price'],
      ],
      'strong_indicators' => [
        'cost', 'sku', 'category', 'description', 'in_stock',
        'product_id', 
      ],
      'weak_indicators' => [
        'image', 'url', 'brand', 'tags',
      ],
    ],
    'customer' => [
      'required_any' => [
        ['name', 'customer_name', 'full_name'],
        ['email', 'contact', 'phone'],
      ],
      'strong_indicators' => [
        'address', 'city', 'state', 'zip', 'country',
        'customer_id', 'account_number',
      ],
      'weak_indicators' => [
        'company', 'birth_date', 'created_date', 'notes',
      ],
    ],
    'vendor' => [
      'required_any' => [
        ['vendor_name', 'supplier_name', 'company_name'],
      ],
      'strong_indicators' => [
        'vendor_id', 'contact', 'email', 'phone', 'address',
        'payment_terms', 'account_number',
      ],
      'weak_indicators' => [
        'tax_id', 'website', 'notes',
      ],
    ],
    'stock_location' => [
      'required_any' => [
        ['location', 'location_name', 'store', 'warehouse'],
      ],
      'strong_indicators' => [
        'location_id', 'store_id', 'address', 'city',
      ],
      'weak_indicators' => [
        'manager', 'phone', 'capacity',
      ],
    ],
  ];
  
  public function __construct(
    private HeaderMatcher $headerMatcher,
  ) {}
  
  /**
   * Detect entity type from headers and sample data
   * 
   * @param array $headers CSV column headers
   * @param array $sampleRows Sample data rows (optional but improves accuracy)
   * @return ServiceResponse with entity_type and confidence
   */
  public function detect(array $headers, array $sampleRows = []): ServiceResponse
  {
    if (empty($headers)) {
      return ServiceResponse::failure(
        errors: ['No headers provided'],
        message: 'Cannot detect entity type without column headers'
      );
    }
    
    $normalizedHeaders = array_map(
      fn($h) => $this->normalizeHeader($h),
      $headers
    );
    
    $scores = [];
    
    foreach (self::ENTITY_SIGNATURES as $entityType => $signature) {
      $score = $this->scoreEntityType($normalizedHeaders, $signature, $sampleRows);
      $scores[$entityType] = $score;
    }
    
    arsort($scores);
    
    $topEntity = array_key_first($scores);
    $topScore = $scores[$topEntity];
    
    if ($topScore < 0.5) {
      return ServiceResponse::failure(
        errors: ['Confidence too low to determine entity type'],
        message: 'Unable to confidently identify data type',
        metadata: [
          'scores' => $scores,
          'headers' => $headers,
        ]
      );
    }
    
    $compositeType = $this->detectCompositeType($scores, $normalizedHeaders);
    
    return ServiceResponse::success(
      data: [
        'entity_type' => $topEntity,
        'confidence' => $topScore,
        'scores' => $scores,
        'composite_type' => $compositeType,
        'explanation' => $this->explainDetection($topEntity, $normalizedHeaders, $topScore),
      ],
      message: sprintf(
        "Detected %s data with %.0f%% confidence",
        $topEntity,
        $topScore * 100
      )
    );
  }

  /**
   * Score how well headers match an entity type signature
   */
  private function scoreEntityType(
    array $normalizedHeaders,
    array $signature,
    array $sampleRows
  ): float {
    $scores = [
      'required' => 0,
      'strong' => 0,
      'weak' => 0,
      'composite' => 0,
    ];
    
    $requiredGroups = $signature['required_any'] ?? [];
    $requiredMatches = 0;
    
    foreach ($requiredGroups as $requiredGroup) {
      if ($this->hasAnyHeader($normalizedHeaders, $requiredGroup)) {
        $requiredMatches++;
      }
    }
    
    $scores['required'] = count($requiredGroups) > 0
      ? $requiredMatches / count($requiredGroups)
      : 0;
    
    if ($scores['required'] < 1.0) {
      return 0;
    }
    
    $strongIndicators = $signature['strong_indicators'] ?? [];
    if (!empty($strongIndicators)) {
      $strongMatches = 0;
      foreach ($strongIndicators as $indicator) {
        if ($this->hasHeader($normalizedHeaders, $indicator)) {
          $strongMatches++;
        }
      }
      $scores['strong'] = $strongMatches / count($strongIndicators);
    }
    
    $weakIndicators = $signature['weak_indicators'] ?? [];
    if (!empty($weakIndicators)) {
      $weakMatches = 0;
      foreach ($weakIndicators as $indicator) {
        if ($this->hasHeader($normalizedHeaders, $indicator)) {
          $weakMatches++;
        }
      }
      $scores['weak'] = $weakMatches / count($weakIndicators);
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
      'required' => 0.4,
      'strong' => 0.35,
      'weak' => 0.15,
      'composite' => 0.10,
    ];
    
    $totalScore = 
      ($scores['required'] * $weights['required']) +
      ($scores['strong'] * $weights['strong']) +
      ($scores['weak'] * $weights['weak']) +
      ($scores['composite'] * $weights['composite']);
    
    return $totalScore;
  }

  /**
   * Check if headers contain any of the specified values
   */
  private function hasAnyHeader(array $normalizedHeaders, array $searchTerms): bool
  {
     foreach ($searchTerms as $term) {
       if ($this->hasHeader($normalizedHeaders, $term)) {
         return true;
       }
     }
     return false;
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
      if ($header === $normalized) {
        return true;
      }
      
      if (str_contains($header, $normalized) || str_contains($normalized, $header)) {
        return true;
      }
      
      if (levenshtein($header, $normalized) <= 2) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Detect if data contains multiple entity types (e.g., orders + line items)
   */
  private function detectCompositeType(array $scores, array $normalizedHeaders): ?array
  {
        
    if (isset($scores['order']) && $scores['order'] > 0.6 &&
        isset($scores['order_item']) && $scores['order_item'] > 0.6) {
      
      return [
        'type' => 'denormalized_transaction',
        'entities' => ['order', 'order_item'],
        'strategy' => 'group_by_transaction_id',
      ];
    }
    
    if (isset($scores['item']) && $scores['item'] > 0.6 &&
        isset($scores['sellable']) && $scores['sellable'] > 0.6) {
      
      return [
        'type' => 'product_catalog',
        'entities' => ['item', 'sellable'],
        'strategy' => 'create_both',
      ];
    }
    
    return null;
  }
  
  /**
   * Generate explanation of why this entity type was detected
   */
  private function explainDetection(
    string $entityType,
    array $normalizedHeaders,
    float $confidence
  ): string {
    $signature = self::ENTITY_SIGNATURES[$entityType];
    
    $parts = ["This appears to be **{$entityType}** data because:"];
    
    foreach ($signature['required_any'] ?? [] as $requiredGroup) {
      $matched = array_filter($requiredGroup, fn($term) => $this->hasHeader($normalizedHeaders, $term));
      
      if (!empty($matched)) {
        $parts[] = sprintf(
          "- Contains required field: **%s**",
          implode('** or **', $matched)
            );
      }
    }
    
    $strongMatches = [];
    foreach ($signature['strong_indicators'] ?? [] as $indicator) {
      if ($this->hasHeader($normalizedHeaders, $indicator)) {
        $strongMatches[] = $indicator;
      }
    }
    
    if (!empty($strongMatches)) {
      $parts[] = sprintf(
        "- Has characteristic fields: **%s**",
        implode('**, **', array_slice($strongMatches, 0, 3))
        );
    }
    
    if ($confidence >= 0.9) {
      $parts[] = "- Very high confidence match";
    } elseif ($confidence >= 0.7) {
      $parts[] = "- High confidence match";
    } else {
      $parts[] = "- Moderate confidence match";
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
    $schemas = [
      'order' => [
        'description' => 'Customer orders or sales transactions',
        'required_fields' => ['order_number', 'order_date'],
        'optional_fields' => ['customer', 'total', 'status', 'payment_method'],
        'relationships' => [
          'has_many' => ['order_items'],
          'belongs_to' => ['customer'],
        ],
      ],
      'order_item' => [
        'description' => 'Line items within an order',
        'required_fields' => ['order_id', 'sellable', 'quantity', 'unit_price'],
        'optional_fields' => ['discount', 'tax', 'notes'],
        'relationships' => [
          'belongs_to' => ['order', 'sellable'],
        ],
      ],
      'item' => [
        'description' => 'Inventory items or raw materials',
        'required_fields' => ['name'],
        'optional_fields' => ['category', 'subcategory', 'sku', 'upc', 'unit'],
        'relationships' => [
          'has_many' => ['sellables'],
        ],
      ],
      'sellable' => [
        'description' => 'Products available for sale',
        'required_fields' => ['name', 'price'],
        'optional_fields' => ['cost', 'sku', 'category', 'description'],
        'relationships' => [
          'belongs_to' => ['item'],
          'has_many' => ['order_items'],
        ],
      ],
    ];
    
    return $schemas[$entityType] ?? [
      'description' => ucfirst($entityType) . ' data',
      'required_fields' => [],
      'optional_fields' => [],
      'relationships' => [],
    ];
  }
}
