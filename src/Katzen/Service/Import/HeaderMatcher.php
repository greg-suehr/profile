<?php

namespace App\Katzen\Service\Import;

/**
 * Header Matcher - Fuzzy matching of CSV column names to entity fields
 * 
 * Uses multiple strategies:
 * - Exact matching
 * - Case-insensitive matching
 * - Levenshtein distance (typo tolerance)
 * - N-gram similarity
 * - Common abbreviations
 * - Synonym matching
 */
final class HeaderMatcher
{
  private const LEVENSHTEIN_THRESHOLD = 3;
  private const NGRAM_SIZE = 2;
  private const SIMILARITY_THRESHOLD = 0.6;

  /**
   * Field definitions by entity type
   * Maps entity fields to common CSV header variations
   */
  private const FIELD_ALIASES = [
    'order' => [
      'order_number' => [
        'order_number', 'order_id', 'orderid', 'order_no', 'orderno',
        'transaction_id', 'trans_id', 'txn_id', 'receipt_no', 'invoice_no',
      ],
      'order_date' => [
        'order_date', 'date', 'transaction_date', 'trans_date', 'txn_date',
        'purchase_date', 'sale_date', 'created_date', 'order_created',
      ],
      'customer' => [
        'customer', 'customer_name', 'client', 'client_name', 'buyer',
        'customer_id', 'client_id', 'account', 'account_name',
      ],
      'status' => [
        'status', 'order_status', 'state', 'order_state',
      ],
      'total' => [
        'total', 'order_total', 'amount', 'total_amount', 'grand_total',
        'final_amount', 'total_price', 'sum',
      ],
      'subtotal' => [
        'subtotal', 'sub_total', 'subtotal_amount', 'line_total',
      ],
      'tax' => [
        'tax', 'tax_amount', 'sales_tax', 'vat', 'gst', 'tax_total',
      ],
      'discount' => [
        'discount', 'discount_amount', 'promo', 'coupon', 'savings',
      ],
    ],
    'order_item' => [
      'order_id' => [
        'order_id', 'order_number', 'transaction_id', 'parent_id',
      ],
      'sellable' => [
        'product', 'product_name', 'item', 'item_name', 'product_detail',
        'sku', 'product_id', 'item_id', 'product_code',
      ],
      'quantity' => [
        'quantity', 'qty', 'amount', 'count', 'units', 'transaction_qty',
        'order_qty', 'qty_ordered',
      ],
      'unit_price' => [
        'unit_price', 'price', 'item_price', 'unit_cost', 'rate',
        'price_each', 'unit_amount',
      ],
      'line_total' => [
        'line_total', 'total', 'amount', 'extended_price', 'ext_price',
        'subtotal', 'line_amount',
      ],
    ],
    'item' => [
      'name' => [
        'name', 'item_name', 'product_name', 'product', 'description',
        'item_description', 'product_detail',
      ],
      'category' => [
        'category', 'product_category', 'item_category', 'type',
        'product_type', 'class', 'group',
      ],
      'subcategory' => [
        'subcategory', 'sub_category', 'subtype', 'sub_type',
        'product_type', 'variant',
      ],
      'sku' => [
        'sku', 'product_code', 'item_code', 'code', 'product_id',
      ],
      'upc' => [
        'upc', 'barcode', 'ean', 'gtin', 'upc_code',
      ],
    ],
    'sellable' => [
      'name' => [
        'name', 'product_name', 'item_name', 'title', 'product',
      ],
      'price' => [
        'price', 'selling_price', 'retail_price', 'sale_price',
        'unit_price', 'list_price',
      ],
      'cost' => [
        'cost', 'unit_cost', 'purchase_price', 'base_cost',
      ],
    ],
    'stock_location' => [
      'name' => [
        'location', 'location_name', 'store', 'store_name', 'warehouse',
        'site', 'branch', 'store_location',
      ],
      'external_id' => [
        'location_id', 'store_id', 'site_id', 'warehouse_id',
      ],
    ],
  ];
  
  /**
   * Common abbreviations and their expansions
   */
  private const ABBREVIATIONS = [
    'qty' => 'quantity',
    'amt' => 'amount',
    'num' => 'number',
    'desc' => 'description',
    'addr' => 'address',
    'pmt' => 'payment',
    'txn' => 'transaction',
    'trans' => 'transaction',
    'cust' => 'customer',
    'prod' => 'product',
    'inv' => 'invoice',
    'rcpt' => 'receipt',
    'ord' => 'order',
    'ln' => 'line',
    'tot' => 'total',
    'sub' => 'subtotal',
    'ext' => 'extended',
    'ea' => 'each',
    'pk' => 'pack',
    'cs' => 'case',
  ];
  
  /**
   * Find the best matching field for a CSV header
   * 
   * @param string $header CSV column name
   * @param string $entityType Target entity type
   * @return array ['field' => string, 'score' => float, 'method' => string]
   */
  public function findBestMatch(string $header, string $entityType): array
  {
    if (!isset(self::FIELD_ALIASES[$entityType])) {
      return ['field' => null, 'score' => 0, 'method' => 'no_schema'];
    }
    
    $normalized = $this->normalizeHeader($header);
    $candidates = [];
    
    foreach (self::FIELD_ALIASES[$entityType] as $field => $aliases) {
      $exactMatch = $this->exactMatch($normalized, $aliases);
      if ($exactMatch) {
        $candidates[$field] = [
          'score' => 1.0,
          'method' => 'exact_match',
        ];
        continue;
      }
      
      $fuzzyScore = $this->fuzzyMatch($normalized, $aliases);
      if ($fuzzyScore > 0) {
        $candidates[$field] = [
          'score' => $fuzzyScore,
          'method' => 'fuzzy_match',
        ];
        continue;
      }
      
      $ngramScore = $this->ngramSimilarity($normalized, $aliases);
      if ($ngramScore > self::SIMILARITY_THRESHOLD) {
        $candidates[$field] = [
          'score' => $ngramScore,
          'method' => 'ngram_similarity',
        ];
        continue;
      }
      
      $substringScore = $this->substringMatch($normalized, $aliases);
      if ($substringScore > 0) {
        $candidates[$field] = [
          'score' => $substringScore,
          'method' => 'substring_match',
        ];
      }
    }
    
    if (empty($candidates)) {
      return ['field' => null, 'score' => 0, 'method' => 'no_match'];
    }
    
    uasort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    
    $bestField = array_key_first($candidates);
    $bestCandidate = $candidates[$bestField];
    
    return [
      'field' => $bestField,
      'score' => $bestCandidate['score'],
      'method' => $bestCandidate['method'],
    ];
  }

  /**
   * Normalize header name for comparison
   */
  private function normalizeHeader(string $header): string
  {
    $normalized = strtolower($header);
    
    $normalized = preg_replace('/^(the|a|an)_/', '', $normalized);
    $normalized = preg_replace('/_(id|name|number|date|amount)$/', '', $normalized);
    
    $normalized = preg_replace('/[\s\-\.]+/', '_', $normalized);
    
    foreach (self::ABBREVIATIONS as $abbr => $full) {
      $normalized = str_replace($abbr, $full, $normalized);
    }
    
    $normalized = trim($normalized, '_');
    
    return $normalized;
  }
  
  /**
   * Exact match against alias list
   */
  private function exactMatch(string $normalized, array $aliases): bool
  {
    foreach ($aliases as $alias) {
      if ($normalized === strtolower($alias)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Fuzzy match using Levenshtein distance
   * 
   * Returns score (0-1) based on how close the strings are
   */
  private function fuzzyMatch(string $normalized, array $aliases): float
  {
    $bestDistance = PHP_INT_MAX;
    $bestAlias = null;
    
    foreach ($aliases as $alias) {
      $distance = levenshtein($normalized, strtolower($alias));
      
      if ($distance < $bestDistance) {
        $bestDistance = $distance;
        $bestAlias = $alias;
      }
    }
    
    if ($bestDistance <= self::LEVENSHTEIN_THRESHOLD) {
      $maxLength = max(strlen($normalized), strlen($bestAlias));
      return 1.0 - ($bestDistance / $maxLength);
    }
    
    return 0;
  }
  
  /**
   * N-gram similarity matching
   * 
   * Breaks strings into character n-grams and compares overlap
   */
  private function ngramSimilarity(string $normalized, array $aliases): float
  {
     $bestScore = 0;

     foreach ($aliases as $alias) {
       $score = $this->calculateNgramSimilarity(
         $normalized, 
         strtolower($alias),
         self::NGRAM_SIZE
       );
       
       $bestScore = max($bestScore, $score);
     }
     
     return $bestScore;
  }
  
  /**
   * Calculate n-gram similarity between two strings
   */
  private function calculateNgramSimilarity(string $str1, string $str2, int $n): float
  {
    $ngrams1 = $this->getNgrams($str1, $n);
    $ngrams2 = $this->getNgrams($str2, $n);
    
    if (empty($ngrams1) || empty($ngrams2)) {
      return 0;
    }
    
    $intersection = count(array_intersect($ngrams1, $ngrams2));
    $union = count(array_unique(array_merge($ngrams1, $ngrams2)));
    
    return $union > 0 ? $intersection / $union : 0;
  }

  /**
   * Generate n-grams from a string
   */
  private function getNgrams(string $str, int $n): array
  {
    $ngrams = [];
    $length = strlen($str);
    
    for ($i = 0; $i <= $length - $n; $i++) {
      $ngrams[] = substr($str, $i, $n);
    }
    
    return $ngrams;
  }

  /**
   * Substring matching - check if normalized header contains or is contained by alias
   */
  private function substringMatch(string $normalized, array $aliases): float
  {
    foreach ($aliases as $alias) {
      $aliasLower = strtolower($alias);
      
      if (str_contains($normalized, $aliasLower)) {
        return 0.7 * (strlen($aliasLower) / strlen($normalized));
      }
      
      if (str_contains($aliasLower, $normalized)) {
        return 0.7 * (strlen($normalized) / strlen($aliasLower));
      }
    }
    
    return 0;
  }

  /**
   * Get all known fields for an entity type
   */
  public function getKnownFields(string $entityType): array
  {
    return array_keys(self::FIELD_ALIASES[$entityType] ?? []);
  }

  /**
   * Get field description for user display
   */
  public function getFieldDescription(string $entityType, string $field): string
  {
    $descriptions = [
      'order' => [
        'order_number' => 'Unique identifier for the order',
        'order_date' => 'Date when the order was placed',
        'customer' => 'Customer who placed the order',
        'status' => 'Current order status (pending, completed, etc.)',
        'total' => 'Total order amount including tax and fees',
        'subtotal' => 'Order amount before tax and fees',
        'tax' => 'Tax amount',
        'discount' => 'Discount or coupon amount',
      ],
      'order_item' => [
        'order_id' => 'Reference to the parent order',
        'sellable' => 'Product or item being sold',
        'quantity' => 'Number of units ordered',
        'unit_price' => 'Price per unit',
        'line_total' => 'Total for this line item (qty × price)',
      ],
    ];
    
    return $descriptions[$entityType][$field] ?? ucwords(str_replace('_', ' ', $field));
  }
  
  /**
   * Suggest alternative fields if confidence is low
   */
  public function suggestAlternatives(
    string $header,
    string $entityType,
    int $limit = 3
  ): array {
    if (!isset(self::FIELD_ALIASES[$entityType])) {
      return [];
    }
    
    $normalized = $this->normalizeHeader($header);
    $scores = [];
    
    foreach (self::FIELD_ALIASES[$entityType] as $field => $aliases) {
      $fuzzyScore = $this->fuzzyMatch($normalized, $aliases);
      $ngramScore = $this->ngramSimilarity($normalized, $aliases);
      $substringScore = $this->substringMatch($normalized, $aliases);
      
      $combinedScore = max($fuzzyScore, $ngramScore, $substringScore);
      
      if ($combinedScore > 0.3) {
        $scores[$field] = $combinedScore;
      }
    }
    
    arsort($scores);
    
    return array_slice($scores, 0, $limit, true);
  }
}
