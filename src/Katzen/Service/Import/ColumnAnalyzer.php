<?php

namespace App\Katzen\Service\Import;

/**
 * Column Analyzer - Infers data types and analyzes value distributions
 * 
 * Examines actual data values to determine:
 * - Data type (integer, decimal, date, string, boolean)
 * - Statistical properties (range, uniqueness, null ratio)
 * - Value patterns and distributions
 */
final class ColumnAnalyzer
{
  private const SAMPLE_SIZE_FOR_INFERENCE = 100;
  
  /**
   * Infer the data type of a column from sample values
   * 
   * @param array $values Sample column values
   * @return array ['type' => string, 'confidence' => float, 'details' => array]
   */
  public function inferDataType(array $values): array
  {
    if (empty($values)) {
      return ['type' => 'unknown', 'confidence' => 0, 'details' => []];
    }
    
    $nonNullValues = array_filter($values, fn($v) => $v !== null && $v !== '');
    
    if (empty($nonNullValues)) {
      return ['type' => 'nullable', 'confidence' => 1.0, 'details' => ['all_null' => true]];
    }
    
    $sample = array_slice($nonNullValues, 0, self::SAMPLE_SIZE_FOR_INFERENCE);
    
    $typeTests = [
      'boolean' => $this->isBooleanColumn($sample),
      'integer' => $this->isIntegerColumn($sample),
      'decimal' => $this->isDecimalColumn($sample),
      'datetime' => $this->isDateTimeColumn($sample),
      'date' => $this->isDateColumn($sample),
      'time' => $this->isTimeColumn($sample),
      'email' => $this->isEmailColumn($sample),
      'phone' => $this->isPhoneColumn($sample),
      'url' => $this->isUrlColumn($sample),
      'currency' => $this->isCurrencyColumn($sample),
      'percentage' => $this->isPercentageColumn($sample),
      'string' => ['matches' => count($sample), 'confidence' => 1.0],
    ];

    $bestType = 'string';
    $bestConfidence = 0;
    $bestDetails = [];
    
    foreach ($typeTests as $type => $result) {
      $confidence = $result['matches'] / count($sample);
      
      if ($confidence > $bestConfidence) {
        $bestType = $type;
        $bestConfidence = $confidence;
        $bestDetails = $result;
      }
    }
    
    return [
      'type' => $bestType,
      'confidence' => $bestConfidence,
      'details' => $bestDetails,
    ];
  }

  /**
   * Analyze value distribution and statistical properties
   */
  public function analyzeDistribution(array $values): array
  {
    $nonNullValues = array_filter($values, fn($v) => $v !== null && $v !== '');
    $totalCount = count($values);
    $nonNullCount = count($nonNullValues);
    
    $uniqueValues = array_unique($nonNullValues);
    $uniqueCount = count($uniqueValues);
    
    $distribution = [
      'total_count' => $totalCount,
      'non_null_count' => $nonNullCount,
      'null_count' => $totalCount - $nonNullCount,
      'null_ratio' => $totalCount > 0 ? ($totalCount - $nonNullCount) / $totalCount : 0,
      'unique_count' => $uniqueCount,
      'uniqueness_ratio' => $nonNullCount > 0 ? $uniqueCount / $nonNullCount : 0,
    ];

    if ($this->isNumeric($nonNullValues)) {
      $numericValues = array_map('floatval', $nonNullValues);

      $safeCount = count($numericValues);

      if ($safeCount == 0) {
        $distribution['range'] = [
          'min' => 0,
          'max' => 0,
          'mean' => 0,
          'median' => 0,
        ];
      } else {
        $distribution['range'] = [
          'min' => min($numericValues),
          'max' => max($numericValues),
          'mean' => array_sum($numericValues) / $safeCount,
          'median' => $this->median($numericValues),
        ];
      }
      $distribution['is_sequential'] = $this->isSequential($numericValues);
    }

    if (!$this->isNumeric($nonNullValues)) {
      $lengths = array_map('strlen', $nonNullValues);
      
      $distribution['length'] = [
        'min' => min($lengths),
        'max' => max($lengths),
        'avg' => array_sum($lengths) / count($lengths),
      ];
    }

    if ($uniqueCount < 20 && $uniqueCount > 0) {
      $valueCounts = array_count_values($nonNullValues);
      arsort($valueCounts);
      
      $distribution['top_values'] = array_slice($valueCounts, 0, 5, true);
    }
    
    return $distribution;
  }

  /**
   * Test if column contains boolean values
   */
  private function isBooleanColumn(array $values): array
  {
    $booleanPatterns = [
      '/^(true|false)$/i',
      '/^(yes|no)$/i',
      '/^(y|n)$/i',
      '/^(1|0)$/',
      '/^(on|off)$/i',
      '/^(enabled|disabled)$/i',
    ];
    
    $matches = 0;
    foreach ($values as $value) {
      $strValue = strtolower(trim($value));
      
      foreach ($booleanPatterns as $pattern) {
        if (preg_match($pattern, $strValue)) {
          $matches++;
          break;
        }
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }
  
  /**
   * Test if column contains integer values
   */
  private function isIntegerColumn(array $values): array
  {
    $matches = 0;
    foreach ($values as $value) {
      if (is_numeric($value) && (int)$value == $value) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }

  /**
   * Test if column contains decimal/float values
   */
  private function isDecimalColumn(array $values): array
  {
    $matches = 0;
    foreach ($values as $value) {
      $cleaned = preg_replace('/[$,\s]/', '', $value);
      
      if (is_numeric($cleaned)) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }

  /**
   * Test if column contains datetime values
   */
  private function isDateTimeColumn(array $values): array
  {
    $matches = 0;
    $detectedFormat = null;
    
    foreach ($values as $value) {
      $result = $this->tryParseDatetime($value);
      
      if ($result['parsed']) {
        $matches++;
        $detectedFormat = $result['format'];
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
      'detected_format' => $detectedFormat,
    ];
    }
  
  /**
   * Test if column contains date values (without time)
   */
  private function isDateColumn(array $values): array
  {
    $matches = 0;
    $detectedFormat = null;
    
    $datePatterns = [
      'Y-m-d' => '/^\d{4}-\d{2}-\d{2}$/',
      'm/d/Y' => '/^\d{1,2}\/\d{1,2}\/\d{4}$/',
      'd/m/Y' => '/^\d{1,2}\/\d{1,2}\/\d{4}$/',
      'm-d-Y' => '/^\d{1,2}-\d{1,2}-\d{4}$/',
      'd.m.Y' => '/^\d{1,2}\.\d{1,2}\.\d{4}$/',
    ];
    
    foreach ($values as $value) {
      foreach ($datePatterns as $format => $pattern) {
        if (preg_match($pattern, $value)) {
          $matches++;
          $detectedFormat = $format;
          break;
        }
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
      'detected_format' => $detectedFormat,
    ];
  }

  /**
   * Test if column contains time values
   */
  private function isTimeColumn(array $values): array
  {
    $matches = 0;
    
    $timePattern = '/^\d{1,2}:\d{2}(:\d{2})?(\s?[AP]M)?$/i';
    
    foreach ($values as $value) {
      if (preg_match($timePattern, trim($value))) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }

  /**
   * Test if column contains email addresses
   */
  private function isEmailColumn(array $values): array
  {
    $matches = 0;
    
    foreach ($values as $value) {
      if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }

  /**
   * Test if column contains phone numbers
   */
  private function isPhoneColumn(array $values): array
  {
    $matches = 0;
        
    $phonePattern = '/^[\d\s\-\(\)\+\.]{7,}$/';

    foreach ($values as $value) {
      if (preg_match($phonePattern, $value)) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }
  
  /**
   * Test if column contains URLs
   */
  private function isUrlColumn(array $values): array
  {
    $matches = 0;
    
    foreach ($values as $value) {
      if (filter_var($value, FILTER_VALIDATE_URL)) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }
  
  /**
   * Test if column contains currency values
   */
  private function isCurrencyColumn(array $values): array
  {
    $matches = 0;
        
    $currencyPattern = '/^[\$€£¥]?\s?[\d,]+\.?\d{0,2}$/';
    
    foreach ($values as $value) {
      if (preg_match($currencyPattern, trim($value))) {
        $matches++;
      }
    }
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }
  
  /**
   * Test if column contains percentage values
   */
  private function isPercentageColumn(array $values): array
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
    
    return [
      'matches' => $matches,
      'confidence' => count($values) > 0 ? $matches / count($values) : 0,
    ];
  }

  /**
   * Try to parse a value as datetime and detect format
   */
  private function tryParseDatetime(string $value): array
  {
    $formats = [
      'Y-m-d H:i:s',
      'Y-m-d H:i',
      'm/d/Y H:i:s',
      'm/d/Y H:i',
      'd/m/Y H:i:s',
      'd/m/Y H:i',
      'Y-m-d\TH:i:s',
      'Y-m-d\TH:i:s\Z',
      'Y-m-d\TH:i:sP',
    ];
    
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $value);
      
      if ($date !== false) {
        return ['parsed' => true, 'format' => $format, 'date' => $date];
      }
    }
    
    $timestamp = strtotime($value);
    if ($timestamp !== false) {
      return ['parsed' => true, 'format' => 'strtotime', 'date' => new \DateTime("@$timestamp")];
    }
    
    return ['parsed' => false];
  }

  /**
   * Check if all values are numeric
   */
  private function isNumeric(array $values): bool
  {
    foreach ($values as $value) {
      if (!is_numeric($value)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Check if numeric values are sequential (likely IDs)
   */
  private function isSequential(array $values): bool
  {
    if (count($values) < 2) {
      return false;
    }
    
    sort($values);
    
    $gaps = [];
    for ($i = 1; $i < count($values); $i++) {
      $gaps[] = $values[$i] - $values[$i - 1];
    }

    $gapsOfOne = count(array_filter($gaps, fn($g) => $g === 1));
    
    return $gapsOfOne / count($gaps) > 0.8;
  }

  /**
   * Calculate median of numeric array
   */
  private function median(array $values): float
  {
    sort($values);
    $count = count($values);
    $middle = floor($count / 2);
    
    if ($count % 2 === 0) {
      return ($values[$middle - 1] + $values[$middle]) / 2;
    }
    
    return $values[$middle];
  }
}
