<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Response\ServiceResponse;
use Psr\Log\LoggerInterface;

/**
 * Import Validator
 * 
 * Validates import data before processing.
 * Performs both structural validation (required fields, data types)
 * and semantic validation (valid references, business rules).
 */
final class ImportValidator
{
  public function __construct(
    private LoggerInterface $logger,
  ) {}
  
  /**
   * Validate an entire batch of rows
   * 
   * @return ServiceResponse with data: ['row_errors' => [row_num => errors], 'warnings' => [...]]
   */
  public function validateBatch(
    array $rows,
    ImportMapping $mapping,
    array $options = []
  ): ServiceResponse {
    $errors = [];
    $warnings = [];
    $validRows = 0;
        
    $fieldMappings = $mapping->getFieldMappings();
    $validationRules = $mapping->getValidationRules() ?? [];
    $entityType = $mapping->getEntityType();
    $requiredFields = $this->getRequiredFields($entityType, $fieldMappings);
        
    foreach ($rows as $row) {
      $rowNumber = $row['_row_number'] ?? 0;
      $rowErrors = [];
      $rowWarnings = [];
      
      foreach ($requiredFields as $field => $sourceColumn) {
        $value = $row[$sourceColumn] ?? null;
        if ($value === null || $value === '') {
          $rowErrors[] = "Required field '{$field}' (column '{$sourceColumn}') is empty";
        }
      }
      
      $typeErrors = $this->validateDataTypes($row, $fieldMappings, $entityType);
      $rowErrors = array_merge($rowErrors, $typeErrors);
      
      if (!empty($validationRules)) {
        $ruleResult = $this->applyValidationRules($row, $validationRules);
        $rowErrors = array_merge($rowErrors, $ruleResult['errors']);
        $rowWarnings = array_merge($rowWarnings, $ruleResult['warnings']);
      }
      
      $entityErrors = $this->validateForEntityType($row, $entityType, $fieldMappings);
      $rowErrors = array_merge($rowErrors, $entityErrors);
      
      if (!empty($rowErrors)) {
        $errors[$rowNumber] = $rowErrors;
      } else {
        $validRows++;
      }
      
      if (!empty($rowWarnings)) {
        $warnings[$rowNumber] = $rowWarnings;
      }
    }
    
    $totalRows = count($rows);
    $errorCount = count($errors);
    
    $this->logger->info('Batch validation completed', [
      'total_rows' => $totalRows,
      'valid_rows' => $validRows,
      'error_rows' => $errorCount,
      'warning_rows' => count($warnings),
    ]);
    
    if ($errorCount > 0) {
      return ServiceResponse::failure(
        errors: array_merge(...array_values($errors)),
        message: sprintf('Validation failed: %d of %d rows have errors', $errorCount, $totalRows),
        data: [
          'row_errors' => $errors,
          'warnings' => $warnings,
          'valid_count' => $validRows,
          'error_count' => $errorCount,
        ]
      );
    }
    
    return ServiceResponse::success(
      data: [
        'valid_count' => $validRows,
        'warnings' => $warnings,
      ],
      message: 'All rows validated successfully'
    );
  }

  /**
   * Validate a single row
   */
  public function validateRow(
    array $row,
    ImportMapping $mapping
  ): ServiceResponse {
    $result = $this->validateBatch([$row], $mapping);
        
    $rowNumber = $row['_row_number'] ?? 0;
    $rowErrors = $result->data['row_errors'][$rowNumber] ?? [];
    
    if (!empty($rowErrors)) {
      return ServiceResponse::failure(
        errors: $rowErrors,
        message: 'Row validation failed'
      );
    }
    
    return ServiceResponse::success(
      message: 'Row is valid'
    );
  }

  /**
   * Get required fields for an entity type
   */
  private function getRequiredFields(string $entityType, array $fieldMappings): array
  {
    $required = $this->getRequiredFieldsForEntityType($entityType);
    $result = [];
    
    foreach ($required as $field) {
      foreach ($fieldMappings as $column => $config) {
        $targetField = is_array($config) ? ($config['target_field'] ?? $config['field'] ?? null) : $config;
        if ($targetField === $field) {
          $result[$field] = $column;
          break;
        }
      }
    }
        
    return $result;
  }

  /**
   * Define required fields per entity type
   */
  private function getRequiredFieldsForEntityType(string $entityType): array
  {
    return match ($entityType) {
      'order' => ['sellable', 'quantity'],
      'order_line' => ['sellable', 'quantity'],
      'purchase' => ['vendor', 'item', 'quantity'],
      'purchase_line' => ['item', 'quantity'],
      'item' => ['name'],
      'sellable' => ['name', 'price'],
      'customer' => ['name'],
      'vendor' => ['name'],
      'stock_location' => ['name'],
      default => [],
    };
  }

  /**
   * Validate data types based on field mappings
   */
  private function validateDataTypes(array $row, array $fieldMappings, string $entityType): array
  {
    $errors = [];
    $typeDefinitions = $this->getTypeDefinitions($entityType);
    
    foreach ($fieldMappings as $column => $config) {
      $targetField = is_array($config) ? ($config['target_field'] ?? $config['field'] ?? null) : $config;
      $value = $row[$column] ?? null;
      
      if ($value === null || $value === '') {
        continue; // Required check handles this
      }
      
      $expectedType = $typeDefinitions[$targetField] ?? null;
      if ($expectedType === null) {
        continue;
      }
      
      $typeError = $this->validateType($value, $expectedType, $column);
      if ($typeError !== null) {
        $errors[] = $typeError;
      }
    }
    
    return $errors;
  }

  /**
   * Define expected types per field
   */
  private function getTypeDefinitions(string $entityType): array
  {
    $common = [
      'quantity' => 'numeric',
      'unit_price' => 'numeric',
      'price' => 'numeric',
      'cost' => 'numeric',
      'total' => 'numeric',
      'tax_rate' => 'numeric',
      'discount' => 'numeric',
      'date' => 'date',
      'order_date' => 'date',
      'scheduled_at' => 'datetime',
      'due_date' => 'date',
      'email' => 'email',
    ];
    
    return match ($entityType) {
      'order', 'order_line' => array_merge($common, [
        'order_number' => 'string',
        'customer' => 'string',
        'sellable' => 'string',
      ]),
      'purchase', 'purchase_line' => array_merge($common, [
        'vendor' => 'string',
        'item' => 'string',
        'purchase_date' => 'date',
      ]),
      'item' => [
        'name' => 'string',
        'sku' => 'string',
        'category' => 'string',
      ],
      'sellable' => array_merge($common, [
        'name' => 'string',
        'sku' => 'string',
      ]),
      default => $common,
    };
  }
  
  /**
   * Validate a single value against expected type
   */
  private function validateType(mixed $value, string $type, string $column): ?string
  {
    $stringValue = (string) $value;
        
    return match ($type) {
      'numeric' => $this->isNumeric($stringValue) 
      ? null 
      : "Column '{$column}' must be numeric, got: '{$stringValue}'",
      
      'integer' => $this->isInteger($stringValue)
      ? null
      : "Column '{$column}' must be an integer, got: '{$stringValue}'",
      
      'date' => $this->isValidDate($stringValue)
      ? null
      : "Column '{$column}' must be a valid date, got: '{$stringValue}'",
      
      'datetime' => $this->isValidDateTime($stringValue)
      ? null
      : "Column '{$column}' must be a valid datetime, got: '{$stringValue}'",
                
      'email' => $this->isValidEmail($stringValue)
      ? null
      : "Column '{$column}' must be a valid email, got: '{$stringValue}'",
      
      'boolean' => $this->isBoolean($stringValue)
      ? null
      : "Column '{$column}' must be a boolean (true/false, yes/no, 1/0), got: '{$stringValue}'",
                
      default => null, // Unknown type, don't validate
    };
  }

  /**
   * Type check helpers
   */
  private function isNumeric(string $value): bool
  {
    $cleaned = str_replace([',', '$', '€', '£', ' '], '', $value);
    return is_numeric($cleaned);
  }
    
  private function isInteger(string $value): bool
  {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
  }
    
  private function isValidDate(string $value): bool
  {
    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y'];
        
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $value);
      if ($date && $date->format($format) === $value) {
        return true;
      }
    }
    
    return strtotime($value) !== false;
  }
    
  private function isValidDateTime(string $value): bool
  {
    $formats = [
      'Y-m-d H:i:s',
      'Y-m-d H:i',
      'Y-m-d\TH:i:s',
      'Y-m-d\TH:i:sP',
      'm/d/Y H:i:s',
      'm/d/Y H:i',
    ];
    
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $value);
      if ($date !== false) {
        return true;
      }
    }
    
    return strtotime($value) !== false;
  }
    
  private function isValidEmail(string $value): bool
  {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
  }
    
  private function isBoolean(string $value): bool
  {
    $lower = strtolower(trim($value));
    return in_array($lower, ['true', 'false', 'yes', 'no', '1', '0', 'y', 'n', 't', 'f'], true);
  }

  /**
   * Apply custom validation rules from mapping
   */
  private function applyValidationRules(array $row, array $rules): array
  {
    $errors = [];
    $warnings = [];
    
    foreach ($rules as $column => $columnRules) {
      $value = $row[$column] ?? null;
      
      foreach ($columnRules as $rule => $ruleConfig) {
        $result = $this->applyRule($value, $rule, $ruleConfig, $column);
        if ($result !== null) {
          if ($ruleConfig['severity'] ?? 'error' === 'warning') {
            $warnings[] = $result;
          } else {
            $errors[] = $result;
          }
        }
      }
    }
    
    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Apply a single validation rule
   */
  private function applyRule(mixed $value, string $rule, mixed $config, string $column): ?string
  {
    $stringValue = (string) $value;
        
    return match ($rule) {
      'min' => is_numeric($stringValue) && (float) $stringValue >= $config
      ? null
      : "Column '{$column}' must be at least {$config}",
      
      'max' => is_numeric($stringValue) && (float) $stringValue <= $config
      ? null
      : "Column '{$column}' must be at most {$config}",
      
      'min_length' => strlen($stringValue) >= $config
      ? null
      : "Column '{$column}' must be at least {$config} characters",
      
      'max_length' => strlen($stringValue) <= $config
      ? null
      : "Column '{$column}' must be at most {$config} characters",
      
      'pattern' => preg_match($config, $stringValue)
      ? null
      : "Column '{$column}' does not match required pattern",
                
      'in' => in_array($stringValue, (array) $config, true)
      ? null
      : "Column '{$column}' must be one of: " . implode(', ', (array) $config),
      
      'not_in' => !in_array($stringValue, (array) $config, true)
      ? null
      : "Column '{$column}' cannot be: " . implode(', ', (array) $config),
                
      default => null,
    };
  }

  /**
   * Entity-specific validation
   */
  private function validateForEntityType(array $row, string $entityType, array $fieldMappings): array
  {
    return match ($entityType) {
      'order', 'order_line' => $this->validateOrderRow($row, $fieldMappings),
      'purchase', 'purchase_line' => $this->validatePurchaseRow($row, $fieldMappings),
      'item' => $this->validateItemRow($row, $fieldMappings),
      'sellable' => $this->validateSellableRow($row, $fieldMappings),
      default => [],
    };
  }

  /**
   * Order-specific validation
   */
  private function validateOrderRow(array $row, array $fieldMappings): array
  {
    $errors = [];
        
    $quantityColumn = $this->findColumnForField('quantity', $fieldMappings);
    if ($quantityColumn && isset($row[$quantityColumn])) {
      $qty = (float) str_replace(',', '', $row[$quantityColumn]);
      if ($qty <= 0) {
        $errors[] = "Order quantity must be positive, got: {$qty}";
      }
    }
    
    $priceColumn = $this->findColumnForField('unit_price', $fieldMappings);
    if ($priceColumn && isset($row[$priceColumn])) {
      $price = (float) str_replace(['$', ',', ' '], '', $row[$priceColumn]);
      if ($price < 0) {
        $errors[] = "Unit price cannot be negative, got: {$price}";
      }
    }
    
    return $errors;
  }

  /**
   * Purchase-specific validation
   */
  private function validatePurchaseRow(array $row, array $fieldMappings): array
  {
    $errors = [];
        
    $quantityColumn = $this->findColumnForField('quantity', $fieldMappings);
    if ($quantityColumn && isset($row[$quantityColumn])) {
      $qty = (float) str_replace(',', '', $row[$quantityColumn]);
      if ($qty <= 0) {
        $errors[] = "Purchase quantity must be positive, got: {$qty}";
      }
    }
    
    return $errors;
  }

  /**
   * Item-specific validation
   */
  private function validateItemRow(array $row, array $fieldMappings): array
  {
    $errors = [];
        
    $nameColumn = $this->findColumnForField('name', $fieldMappings);
    if ($nameColumn && isset($row[$nameColumn])) {
      $name = trim($row[$nameColumn]);
      if (strlen($name) < 2) {
        $errors[] = "Item name is too short: '{$name}'";
      }
    }
    
    return $errors;
  }

  /**
   * Sellable-specific validation
   */
  private function validateSellableRow(array $row, array $fieldMappings): array
  {
    $errors = [];
        
    $priceColumn = $this->findColumnForField('price', $fieldMappings);
    if ($priceColumn && isset($row[$priceColumn])) {
      $price = (float) str_replace(['$', ',', ' '], '', $row[$priceColumn]);
      if ($price <= 0) {
        $errors[] = "Sellable price must be positive, got: {$price}";
      }
    }
    
    return $errors;
  }

  /**
   * Find column name that maps to a target field
   */
  private function findColumnForField(string $field, array $fieldMappings): ?string
  {
    foreach ($fieldMappings as $column => $config) {
      $targetField = is_array($config) ? ($config['target_field'] ?? $config['field'] ?? null) : $config;
      if ($targetField === $field) {
        return $column;
      }
    }
    return null;
  }
}
