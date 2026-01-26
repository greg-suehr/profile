<?php

namespace App\Katzen\Service\Import;

/**
 * Pattern Recognizer - Detects specific data patterns in column values
 * 
 * Recognizes:
 * - ID patterns (sequential, UUID, formatted codes)
 * - Currency patterns (with symbols, formatting)
 * - Date/time patterns and formats
 * - Phone number patterns
 * - Email patterns
 * - SKU/barcode patterns
 * - Percentage patterns
 * - Quantity patterns (with units)
 */
final class PatternRecognizer
{
  /**
   * Detect patterns in a column's values
   * 
   * @param array $values Column values to analyze
   * @return array Detected patterns with confidence scores
   */
  public function detectPattern(array $values): array
  {
    $nonNullValues = array_filter($values, fn($v) => $v !== null && $v !== '');
        
    if (empty($nonNullValues)) {
      return ['patterns' => [], 'primary' => null];
    }
    
    $sample = array_slice($nonNullValues, 0, 100);
    
    $patterns = [
      'sequential_id' => $this->detectSequentialId($sample),
      'uuid' => $this->detectUUID($sample),
      'formatted_id' => $this->detectFormattedId($sample),
      'currency_usd' => $this->detectCurrencyUSD($sample),
      'currency_symbol' => $this->detectCurrencySymbol($sample),
      'date_iso' => $this->detectDateISO($sample),
      'date_us' => $this->detectDateUS($sample),
      'date_european' => $this->detectDateEuropean($sample),
      'datetime_iso' => $this->detectDateTimeISO($sample),
      'time_12h' => $this->detectTime12Hour($sample),
      'time_24h' => $this->detectTime24Hour($sample),
      'email' => $this->detectEmail($sample),
      'phone' => $this->detectPhone($sample),
      'sku' => $this->detectSKU($sample),
      'upc' => $this->detectUPC($sample),
      'percentage' => $this->detectPercentage($sample),
      'quantity_with_unit' => $this->detectQuantityWithUnit($sample),
    ];
    
    $detectedPatterns = array_filter($patterns, fn($p) => $p['confidence'] > 0);

    uasort($detectedPatterns, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
    
    $primary = !empty($detectedPatterns) ? array_values($detectedPatterns)[0] : null;
    
    return [
      'patterns' => $detectedPatterns,
      'primary' => $primary,
    ];
  }
  
  /**
   * Detect sequential ID pattern (1, 2, 3... or 1001, 1002, 1003...)
   */
  private function detectSequentialId(array $values): array
  {
    $numericValues = array_filter($values, 'is_numeric');
    
    if (count($numericValues) / count($values) < 0.9) {
      return ['confidence' => 0];
    }
    
    $intValues = array_map('intval', $numericValues);
    sort($intValues);
    
    $gaps = [];
    for ($i = 1; $i < count($intValues); $i++) {
      $gaps[] = $intValues[$i] - $intValues[$i - 1];
    }
    
    $sequentialGaps = count(array_filter($gaps, fn($g) => $g === 1));
    $confidence = count($gaps) > 0 ? $sequentialGaps / count($gaps) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'Sequential ID',
        'field' => 'id',
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect UUID pattern
   */
  private function detectUUID(array $values): array
  {
    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($uuidPattern, $value)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'UUID',
        'field' => 'uuid',
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect formatted ID (ORD-12345, INV-2024-001, etc.)
   */
  private function detectFormattedId(array $values): array
  {
    $pattern = '/^[A-Z]{2,4}[-_]\d{3,}$/i';
        
    $matches = 0;
    $prefix = null;
    
    foreach ($values as $value) {
      if (preg_match($pattern, $value)) {
        $matches++;
        
        if (!$prefix && preg_match('/^([A-Z]+)[-_]/', $value, $m)) {
          $prefix = $m[1];
        }
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      $fieldSuggestion = match(strtoupper($prefix)) {
        'ORD', 'ORDER' => 'order_number',
        'INV', 'INVOICE' => 'invoice_number',
        'CUST', 'CUSTOMER' => 'customer_id',
        'PROD', 'PRODUCT' => 'product_id',
        default => 'external_id',
      };
      
      return [
        'confidence' => $confidence,
        'name' => 'Formatted ID',
        'field' => $fieldSuggestion,
        'prefix' => $prefix,
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect US currency format ($1,234.56)
   */
  private function detectCurrencyUSD(array $values): array
  {
    $pattern = '/^\$\s?[\d,]+\.?\d{0,2}$/';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value))) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'USD Currency',
        'field' => 'price',
        'transformation' => [
          'type' => 'currency_to_decimal',
          'remove_symbols' => ['$', ','],
        ],
      ];
    }
    
    return ['confidence' => 0];
  }
  
  /**
   * Detect currency with any symbol (€, £, ¥, etc.)
   */
  private function detectCurrencySymbol(array $values): array
  {
    $pattern = '/^[€£¥₹]\s?[\d.,]+$/';
        
    $matches = 0;
    $symbol = null;
    
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value), $m)) {
        $matches++;
        if (!$symbol) {
          $symbol = $m[1] ?? null;
        }
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'Currency',
        'field' => 'price',
        'symbol' => $symbol,
        'transformation' => [
          'type' => 'currency_to_decimal',
          'remove_symbols' => [$symbol, ',', '.'],
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect ISO date format (YYYY-MM-DD)
   */
  private function detectDateISO(array $values): array
  {
    $pattern = '/^\d{4}-\d{2}-\d{2}$/';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, $value)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'ISO Date',
        'field' => 'date',
        'transformation' => [
          'type' => 'parse_date',
          'format' => 'Y-m-d',
        ],
      ];
    }
    
    return ['confidence' => 0];
    }
  
  /**
   * Detect US date format (MM/DD/YYYY)
   */
  private function detectDateUS(array $values): array
  {
    $pattern = '/^\d{1,2}\/\d{1,2}\/\d{4}$/';
    
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, $value)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'US Date',
        'field' => 'date',
        'transformation' => [
          'type' => 'parse_date',
          'format' => 'm/d/Y',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect European date format (DD/MM/YYYY)
   */
  private function detectDateEuropean(array $values): array
  {
    $pattern = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
        
    $matches = 0;
    $likelyEuropean = 0;
    
    foreach ($values as $value) {
      if (preg_match($pattern, $value, $m)) {
        $matches++;
        
        $part1 = (int)$m[1];
        $part2 = (int)$m[2];
        
        if ($part1 > 12) {
          $likelyEuropean++;
        }
        elseif ($part2 > 12) {
        }
      }
    }
    
    $confidence = ($matches > 0 && $likelyEuropean / $matches > 0.3) 
            ? $matches / count($values) 
      : 0;
    
    if ($confidence > 0.6) {
      return [
        'confidence' => $confidence,
        'name' => 'European Date',
        'field' => 'date',
        'transformation' => [
          'type' => 'parse_date',
          'format' => 'd/m/Y',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect ISO datetime (YYYY-MM-DD HH:MM:SS)
   */
  private function detectDateTimeISO(array $values): array
  {
    $pattern = '/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}(:\d{2})?/';
    
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, $value)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'ISO DateTime',
        'field' => 'datetime',
        'transformation' => [
          'type' => 'parse_datetime',
          'format' => 'Y-m-d H:i:s',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }
  
  /**
   * Detect 12-hour time (3:45 PM, 11:30 AM)
   */
  private function detectTime12Hour(array $values): array
  {
    $pattern = '/^\d{1,2}:\d{2}(:\d{2})?\s?[AP]M$/i';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value))) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => '12-Hour Time',
        'field' => 'time',
        'transformation' => [
          'type' => 'parse_time',
          'format' => 'g:i A',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }
  
  /**
   * Detect 24-hour time (15:45, 23:30)
   */
  private function detectTime24Hour(array $values): array
  {
    $pattern = '/^([01]?\d|2[0-3]):\d{2}(:\d{2})?$/';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value))) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => '24-Hour Time',
        'field' => 'time',
        'transformation' => [
          'type' => 'parse_time',
          'format' => 'H:i:s',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect email addresses
   */
  private function detectEmail(array $values): array
  {
    $matches = 0;
    foreach ($values as $value) {
      if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'Email',
        'field' => 'email',
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect phone numbers
   */
  private function detectPhone(array $values): array
  {
    $pattern = '/^[\d\s\-\(\)\+\.]{7,}$/';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, $value)) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'Phone Number',
        'field' => 'phone',
        'transformation' => [
          'type' => 'normalize_phone',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect SKU patterns (alphanumeric codes)
   */
  private function detectSKU(array $values): array
  {
    $pattern = '/^[A-Z0-9]{4,20}$/i';
    
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value))) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'SKU',
        'field' => 'sku',
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect UPC/barcode (numeric, typically 12-13 digits)
   */
  private function detectUPC(array $values): array
  {
    $pattern = '/^\d{12,13}$/';
        
    $matches = 0;
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value))) {
        $matches++;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'UPC',
        'field' => 'upc',
        'transformation' => null,
      ];
    }
    
    return ['confidence' => 0];
  }
  
  /**
   * Detect percentage values
   */
  private function detectPercentage(array $values): array
  {
    $matches = 0;
    foreach ($values as $value) {
      $trimmed = trim($value);
      
      if (str_ends_with($trimmed, '%')) {
        $number = substr($trimmed, 0, -1);
        if (is_numeric($number)) {
          $matches++;
        }
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      return [
        'confidence' => $confidence,
        'name' => 'Percentage',
        'field' => 'percentage',
        'transformation' => [
          'type' => 'percentage_to_decimal',
        ],
      ];
    }
    
    return ['confidence' => 0];
  }

  /**
   * Detect quantity with unit (5 kg, 10 lbs, 3.5 oz)
   */
  private function detectQuantityWithUnit(array $values): array
  {
    $pattern = '/^(\d+\.?\d*)\s?(kg|g|mg|lb|lbs|oz|ml|l|gal|qt|pt|ea|each|pcs|units?)$/i';
        
    $matches = 0;
    $commonUnit = null;
    $unitCounts = [];
    
    foreach ($values as $value) {
      if (preg_match($pattern, trim($value), $m)) {
        $matches++;
        
        $unit = strtolower($m[2] ?? '');
        $unitCounts[$unit] = ($unitCounts[$unit] ?? 0) + 1;
      }
    }
    
    $confidence = count($values) > 0 ? $matches / count($values) : 0;
    
    if ($confidence > 0.8) {
      arsort($unitCounts);
      $commonUnit = array_key_first($unitCounts);
      
      return [
        'confidence' => $confidence,
        'name' => 'Quantity with Unit',
        'field' => 'quantity',
        'common_unit' => $commonUnit,
        'transformation' => [
          'type' => 'extract_quantity',
          'target_unit' => $commonUnit,
        ],
      ];
    }
    
    return ['confidence' => 0];
  }
}
