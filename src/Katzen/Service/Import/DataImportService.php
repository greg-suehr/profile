<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportError;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Repository\Import\ImportBatchRepository;
use App\Katzen\Repository\Import\ImportErrorRepository;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Data Import Service
 * 
 * Orchestrates the complete import process from file to database entities.
 * Handles CSV/Excel parsing, validation, master data extraction, and 
 * transactional data creation with full rollback support.
 * 
 * Import Flow:
 * 1. Create batch record for tracking
 * 2. Parse and validate file structure
 * 3. Pre-validate all rows (fail fast on critical errors)
 * 4. Extract and create master data (Items, Sellables, Locations, Customers)
 * 5. Process transactional data in batches (Orders, Purchases, etc.)
 * 6. Generate derived data (accounting entries, inventory adjustments)
 * 7. Mark batch complete
 */
final class DataImportService
{
  private const DEFAULT_BATCH_SIZE = 500;
  private const MAX_ERROR_PERCENTAGE = 10;
  private const MAX_ERRORS_TO_STORE = 1000;
    
  public function __construct(
    private EntityManagerInterface $em,
    private ImportMappingService $mappingService,
    private ImportValidator $validator,
    private MasterDataExtractor $masterDataExtractor,
    private TransactionImportService $transactionImporter,
    private ImportBatchRepository $batchRepo,
    private ImportErrorRepository $errorRepo,
    private LoggerInterface $logger,
  ) {}
  
  /**
   * Main import entry point - orchestrates the entire process
   * 
   * @param string $filepath Path to the CSV/Excel file
   * @param ImportMapping $mapping Field mapping configuration
   * @param array $options {
   *   @type int $batch_size Rows per processing batch (default: 500)
   *   @type bool $skip_master_data Skip master data extraction (default: false)
   *   @type bool $generate_accounting Generate accounting entries (default: true)
   *   @type bool $dry_run Validate only, don't persist (default: false)
   *   @type int $created_by User ID creating the import
   *   @type callable $progress_callback fn(int $processed, int $total, string $phase)
   *   @type int $resume_from_row Resume from this row number (for crashed imports)
   *   @type float $max_error_percentage Abort threshold (default: 10)
   * }
   */
  public function importFromFile(
    string $filepath,
    ImportMapping $mapping,
    array $options = []
  ): ServiceResponse {
    $batchSize = $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE;
    $maxErrorPercentage = $options['max_error_percentage'] ?? self::MAX_ERROR_PERCENTAGE;
    $progressCallback = $options['progress_callback'] ?? null;
    $dryRun = $options['dry_run'] ?? false;
    $resumeFromRow = $options['resume_from_row'] ?? 0;
    
    $batch = $this->createBatch($filepath, $mapping, $options);
    
    try {
      $this->notifyProgress($progressCallback, 0, 0, 'parsing');
      
      $parseResult = $this->parseFile($filepath);
      if ($parseResult->isFailure()) {
        return $this->failBatch($batch, $parseResult->errors);
      }
      
      $rows = $parseResult->data['rows'];
      $headers = $parseResult->data['headers'];
      $totalRows = count($rows);
      
      $batch->setTotalRows($totalRows);
      $this->batchRepo->save($batch);
      
      $this->logger->info('File parsed successfully', [
        'batch_id' => $batch->getId(),
        'total_rows' => $totalRows,
        'headers' => $headers,
      ]);
      
      $this->notifyProgress($progressCallback, 0, $totalRows, 'validating');
      
      $validationResult = $this->validator->validateBatch(
        $rows, 
        $mapping,
        ['headers' => $headers]
      );
      
      if ($validationResult->isFailure()) {
        $errorCount = count($validationResult->errors);
        $errorPercentage = ($errorCount / $totalRows) * 100;
        
        if ($errorPercentage > $maxErrorPercentage) {
          $this->storeValidationErrors($batch, $validationResult->data['row_errors'] ?? []);
          return $this->failBatch($batch, [
            sprintf(
              'Validation failed: %d errors (%.1f%%) exceeds threshold of %.1f%%',
              $errorCount,
              $errorPercentage,
              $maxErrorPercentage
            ),
          ], $validationResult->data);
        }
        
        $this->storeValidationErrors(
          $batch, 
          $validationResult->data['row_errors'] ?? [],
          ImportError::SEVERITY_WARNING
        );
      }
      
      if ($resumeFromRow > 0) {
        $rows = array_slice($rows, $resumeFromRow);
        $this->logger->info('Resuming import', [
          'batch_id' => $batch->getId(),
          'resume_from' => $resumeFromRow,
          'remaining_rows' => count($rows),
        ]);
      }
      
      if ($dryRun) {
        return ServiceResponse::success(
          data: [
            'batch_id' => $batch->getId(),
            'total_rows' => $totalRows,
            'validation_passed' => $validationResult->isSuccess(),
            'dry_run' => true,
          ],
          message: 'Dry run completed successfully'
        );
      }
            
      $this->notifyProgress($progressCallback, 0, $totalRows, 'master_data');
      $batch->setStatus(ImportBatch::STATUS_PROCESSING);
      $batch->setStartedAt(new \DateTimeImmutable());
      $this->batchRepo->save($batch);
      
      $entityCounts = [
        'locations' => 0,
        'items' => 0,
        'sellables' => 0,
        'customers' => 0,
        'vendors' => 0,
      ];
      
      if (!($options['skip_master_data'] ?? false)) {
        $masterResult = $this->processMasterData($rows, $mapping, $batch);
        if ($masterResult->isFailure()) {
          return $this->failBatch($batch, $masterResult->errors);
        }
        $entityCounts = array_merge($entityCounts, $masterResult->data['entity_counts'] ?? []);
      }
      
      $this->notifyProgress($progressCallback, 0, $totalRows, 'transactions');
      
      $transactionResult = $this->processTransactions(
        $rows,
        $mapping,
        $batch,
        $batchSize,
        $resumeFromRow,
        $progressCallback
      );
      
      $batch->setEntityCounts(array_merge(
        $entityCounts,
        $transactionResult->data['entity_counts'] ?? []
      ));
      
      if ($options['generate_accounting'] ?? true) {
        $this->notifyProgress($progressCallback, $totalRows, $totalRows, 'accounting');
        $this->generateDerivedData($batch);
      }
      
      $batch->setStatus(ImportBatch::STATUS_COMPLETED);
      $batch->setCompletedAt(new \DateTimeImmutable());
      $batch->setErrorSummary($this->generateErrorSummary($batch));
      $this->batchRepo->save($batch);
      
      $this->notifyProgress($progressCallback, $totalRows, $totalRows, 'complete');
      
      return ServiceResponse::success(
        data: [
          'batch_id' => $batch->getId(),
          'total_rows' => $batch->getTotalRows(),
          'successful_rows' => $batch->getSuccessfulRows(),
          'failed_rows' => $batch->getFailedRows(),
          'entity_counts' => $batch->getEntityCounts(),
          'error_summary' => $batch->getErrorSummary(),
        ],
        message: sprintf(
          'Import completed: %d/%d rows successful',
          $batch->getSuccessfulRows(),
          $batch->getTotalRows()
                )
            );
      
    } catch (\Throwable $e) {
      $this->logger->error('Import failed with exception', [
        'batch_id' => $batch->getId(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      
      return $this->failBatch($batch, ['Import failed: ' . $e->getMessage()]);
    }
  }
  
  /**
   * Convenience method for CSV imports (backwards compatibility)
   */
  public function importFromCSV(
    string $filepath,
    ImportMapping $mapping,
    array $options = []
  ): ServiceResponse {
    return $this->importFromFile($filepath, $mapping, $options);
  }
  
  /**
   * Create the batch tracking record
   */
  private function createBatch(
    string $filepath, 
    ImportMapping $mapping, 
    array $options
    ): ImportBatch {
    $batch = new ImportBatch();
    $batch->setName($this->generateBatchName($filepath, $mapping));
    $batch->setMapping($mapping);
    $batch->setSourceFile(basename($filepath));
    $batch->setSourceFilePath($filepath);
    $batch->setStatus(ImportBatch::STATUS_PENDING);
    
    if (isset($options['created_by'])) {
      $batch->setCreatedBy($options['created_by']);
    }
    
    $this->batchRepo->save($batch);
    
    $this->logger->info('Import batch created', [
      'batch_id' => $batch->getId(),
      'mapping_id' => $mapping->getId(),
      'file' => basename($filepath),
    ]);
    
    return $batch;
  }
    
  /**
   * Generate a descriptive batch name
   */
  private function generateBatchName(string $filepath, ImportMapping $mapping): string
  {
    $filename = pathinfo($filepath, PATHINFO_FILENAME);
    $date = (new \DateTime())->format('Y-m-d H:i');
    
    return sprintf('%s - %s (%s)', $mapping->getName(), $filename, $date);
  }

  /**
   * Parse CSV or Excel file into rows
   * 
   * @return ServiceResponse with data: ['rows' => array, 'headers' => array]
   */
  private function parseFile(string $filepath): ServiceResponse
  {
    if (!file_exists($filepath)) {
      return ServiceResponse::failure(
        errors: ["File not found: {$filepath}"]
      );
    }
    
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    return match ($extension) {
      'csv', 'txt' => $this->parseCSV($filepath),
      'xlsx', 'xls' => $this->parseExcel($filepath),
      default => ServiceResponse::failure(
        errors: ["Unsupported file format: {$extension}. Supported: csv, xlsx, xls"]
      ),
    };
  }

  # TODO: move parseCSV to CSVParser class
  /**
   * Parse CSV file
   */
  private function parseCSV(string $filepath): ServiceResponse
  {
    $rows = [];
    $headers = [];
    
    try {
      $handle = fopen($filepath, 'r');
      if ($handle === false) {
        return ServiceResponse::failure(
          errors: ["Unable to open file: {$filepath}"]
        );
      }
      
      $firstLine = fgets($handle);
      rewind($handle);
      $delimiter = $this->detectDelimiter($firstLine);
      
      $headers = fgetcsv($handle, 0, $delimiter);
      if ($headers === false) {
        fclose($handle);
        return ServiceResponse::failure(
          errors: ['Unable to read CSV headers']
        );
      }
            
      $headers = array_map('trim', $headers);
      
      $rowNumber = 1;
      while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNumber++;
        
        if ($this->isEmptyRow($row)) {
          continue;
        }
        
        if (count($row) === count($headers)) {
          $rows[] = [
            '_row_number' => $rowNumber,
            ...$this->combineHeadersWithRow($headers, $row),
          ];
        } else {
          $this->logger->warning('Column count mismatch', [
            'row' => $rowNumber,
            'expected' => count($headers),
            'actual' => count($row),
          ]);
          
          $paddedRow = array_pad($row, count($headers), null);
          $rows[] = [
            '_row_number' => $rowNumber,
            '_column_mismatch' => true,
            ...$this->combineHeadersWithRow($headers, array_slice($paddedRow, 0, count($headers))),
          ];
        }
      }
      
      fclose($handle);
      
      return ServiceResponse::success(
        data: [
          'rows' => $rows,
          'headers' => $headers,
          'row_count' => count($rows),
        ]
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['CSV parsing failed: ' . $e->getMessage()]
      );
    }
  }
    
  /**
   * Parse Excel file (requires PhpSpreadsheet)
   */
  private function parseExcel(string $filepath): ServiceResponse
  {
    try {
      if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
        return ServiceResponse::failure(
          errors: ['Excel support requires phpoffice/phpspreadsheet package']
        );
      }
      
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
      $worksheet = $spreadsheet->getActiveSheet();
      
      $rows = [];
      $headers = [];
      $rowNumber = 0;
      
      foreach ($worksheet->getRowIterator() as $row) {
        $rowNumber++;
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        $rowData = [];
        foreach ($cellIterator as $cell) {
          $rowData[] = $cell->getValue();
        }
        
        if ($rowNumber === 1) {
          $headers = array_map('trim', $rowData);
          continue;
        }
        
        if ($this->isEmptyRow($rowData)) {
          continue;
        }
        
        $rows[] = [
          '_row_number' => $rowNumber,
          ...$this->combineHeadersWithRow($headers, $rowData),
        ];
      }
      
      return ServiceResponse::success(
        data: [
          'rows' => $rows,
          'headers' => $headers,
          'row_count' => count($rows),
        ]
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Excel parsing failed: ' . $e->getMessage()]
      );
    }
  }
    
  /**
   * Detect CSV delimiter from first line
   */
  private function detectDelimiter(string $line): string
  {
    $delimiters = [',', "\t", ';', '|'];
    $counts = [];
    
    foreach ($delimiters as $delimiter) {
      $counts[$delimiter] = substr_count($line, $delimiter);
    }
        
    arsort($counts);
    return array_key_first($counts) ?? ',';
  }
    
  /**
   * Check if row is empty
   */
  private function isEmptyRow(array $row): bool
  {
     return empty(array_filter($row, fn($val) => $val !== null && $val !== ''));
  }
    
  /**
   * Combine headers with row values
   */
  private function combineHeadersWithRow(array $headers, array $row): array
  {
    $result = [];
    foreach ($headers as $i => $header) {
      $result[$header] = $row[$i] ?? null;
    }
    return $result;
  }

  /**
   * Process master data extraction and creation
   */
  private function processMasterData(
    array $rows,
    ImportMapping $mapping,
    ImportBatch $batch
  ): ServiceResponse {
    $this->logger->info('Starting master data extraction', [
      'batch_id' => $batch->getId(),
      'row_count' => count($rows),
    ]);
        
    try {
      $masterData = $this->masterDataExtractor->extract($rows, $mapping);
            
      $entityCounts = [
        'locations' => 0,
        'items' => 0,
        'sellables' => 0,
        'customers' => 0,
        'vendors' => 0,
      ];
            
      $createdEntities = [];
            
      # TODO Process each entity type via ItemService, ... , etc.
      # For now, we delegate to the MasterDataExtractor which can handle creation
      
      $createResult = $this->masterDataExtractor->createEntities(
        $masterData,
        $mapping->getEntityType(),
        $batch
      );
      
      if ($createResult->isFailure()) {
        return $createResult;
      }
      
      $entityCounts = $createResult->data['entity_counts'] ?? $entityCounts;
      
      $batch->setEntityCounts($entityCounts);
      $this->batchRepo->save($batch);
      
      $this->logger->info('Master data extraction complete', [
        'batch_id' => $batch->getId(),
        'entity_counts' => $entityCounts,
      ]);
      
      return ServiceResponse::success(
        data: ['entity_counts' => $entityCounts]
      );
      
    } catch (\Throwable $e) {
      $this->logger->error('Master data extraction failed', [
        'batch_id' => $batch->getId(),
        'error' => $e->getMessage(),
      ]);
      
      return ServiceResponse::failure(
        errors: ['Master data extraction failed: ' . $e->getMessage()]
      );
    }
  }

  /**
   * Process transactional data in batches
   * 
   * Uses a detached approach to avoid EntityManager issues after clear()
   */
  private function processTransactions(
    array $rows,
    ImportMapping $mapping,
    ImportBatch $batch,
    int $batchSize,
    int $resumeFromRow,
    ?callable $progressCallback
  ): ServiceResponse {
    $chunks = array_chunk($rows, $batchSize, true);
    $processedCount = $batch->getProcessedRows();
    $successCount = $batch->getSuccessfulRows();
    $failureCount = $batch->getFailedRows();
    $batchId = $batch->getId();
    $totalRows = count($rows) + $resumeFromRow;
    
    $entityCounts = [
      'orders' => 0,
      'order_items' => 0,
      'purchases' => 0,
      'purchase_items' => 0,
    ];
    
    $pendingErrors = [];
    
    foreach ($chunks as $chunkIndex => $chunk) {
      $this->logger->info('Processing chunk', [
        'batch_id' => $batchId,
        'chunk' => $chunkIndex + 1,
        'total_chunks' => count($chunks),
        'chunk_size' => count($chunk),
      ]);
      
      $this->em->beginTransaction();
      
      try {
        foreach ($chunk as $row) {
          $actualRowNumber = $row['_row_number'] ?? ($processedCount + 1);
          
          $result = $this->transactionImporter->importTransaction(
            $row,
            $mapping,
            $batchId
          );
          
          if ($result->isSuccess()) {
            $successCount++;
            
            if (isset($result->data['entity_type'])) {
              $key = $result->data['entity_type'] . 's';
              $entityCounts[$key] = ($entityCounts[$key] ?? 0) + 1;
            }
          } else {
            $failureCount++;
                        
            if (count($pendingErrors) < self::MAX_ERRORS_TO_STORE) {
              $pendingErrors[] = $this->createError(
                $batchId,
                $actualRowNumber,
                $result->errors,
                $row,
                ImportError::TYPE_ENTITY_CREATION
              );
            }
          }
          
          $processedCount++;
        }
        
        $this->em->commit();
        $this->em->flush();
        
        $this->em->clear();
        
        if (!empty($pendingErrors)) {
          $this->errorRepo->saveBatch($pendingErrors, 50);
          $pendingErrors = [];
        }
        
        $this->batchRepo->updateProgress($batchId, $processedCount, $successCount, $failureCount);
        
        $this->notifyProgress($progressCallback, $processedCount, $totalRows, 'transactions');
        
      } catch (\Throwable $e) {
        $this->em->rollback();
        
        $this->logger->error('Chunk processing failed', [
          'batch_id' => $batchId,
          'chunk' => $chunkIndex + 1,
          'error' => $e->getMessage(),
        ]);
                
        foreach ($chunk as $row) {
          $actualRowNumber = $row['_row_number'] ?? ($processedCount + 1);
          
          if (count($pendingErrors) < self::MAX_ERRORS_TO_STORE) {
            $pendingErrors[] = $this->createError(
              $batchId,
              $actualRowNumber,
              ['Batch transaction failed: ' . $e->getMessage()],
              $row,
              ImportError::TYPE_ENTITY_CREATION,
              ImportError::SEVERITY_CRITICAL
            );
          }
          
          $failureCount++;
          $processedCount++;
        }
        
        $this->errorRepo->saveBatch($pendingErrors, 50);
        $pendingErrors = [];
        
        $this->batchRepo->updateProgress($batchId, $processedCount, $successCount, $failureCount);
      }
    }
    
    return ServiceResponse::success(
      data: [
        'processed' => $processedCount,
        'successful' => $successCount,
        'failed' => $failureCount,
        'entity_counts' => $entityCounts,
      ]
    );
  }
  
  /**
   * Create an error entity (without persisting)
   */
  private function createError(
    int $batchId,
    int $rowNumber,
    array $errors,
    array $rowData,
    string $type = ImportError::TYPE_VALIDATION,
    string $severity = ImportError::SEVERITY_ERROR
  ): ImportError {
    $batch = $this->em->getReference(ImportBatch::class, $batchId);
        
    $error = new ImportError();
    $error->setBatch($batch);
    $error->setRowNumber($rowNumber);
    $error->setErrorType($type);
    $error->setSeverity($severity);
    $error->setErrorMessage(implode('; ', $errors));
    $error->setRowData($this->sanitizeRowData($rowData));
    
    return $error;
  }
    
  /**
   * Remove internal metadata from row data before storing
   */
  private function sanitizeRowData(array $row): array
  {
    unset($row['_row_number'], $row['_column_mismatch']);
    return $row;
  }

  /**
   * Store validation errors
   */
  private function storeValidationErrors(
    ImportBatch $batch,
    array $rowErrors,
    string $severity = ImportError::SEVERITY_ERROR
  ): void {
    $errors = [];
    $count = 0;
        
    foreach ($rowErrors as $rowNumber => $messages) {
      if ($count >= self::MAX_ERRORS_TO_STORE) {
        $this->logger->warning('Error storage limit reached', [
          'batch_id' => $batch->getId(),
          'stored' => $count,
          'total' => count($rowErrors),
        ]);
        break;
      }
            
      $error = new ImportError();
      $error->setBatch($batch);
      $error->setRowNumber($rowNumber);
      $error->setErrorType(ImportError::TYPE_VALIDATION);
      $error->setSeverity($severity);
      $error->setErrorMessage(implode('; ', (array) $messages));
      
      $errors[] = $error;
      $count++;
    }
    
    if (!empty($errors)) {
      $this->errorRepo->saveBatch($errors);
    }
  }

  /**
   * Generate derived data (accounting entries, inventory adjustments)
   */
  private function generateDerivedData(ImportBatch $batch): void
  {
    $this->logger->info('Generating derived data', [
      'batch_id' => $batch->getId(),
    ]);
        
    # TODO: This will be entity-type specific
    # For orders: might generate AR entries
    # For purchases: might generate AP entries
    # For inventory: might adjust stock levels
    
    $entityType = $batch->getMapping()->getEntityType();
    
    # TODO: Implement per-type generators
    # $this->accountingGenerator->generateForBatch($batch);
    # $this->inventoryGenerator->adjustForBatch($batch);
  }
  
  /**
   * Generate error summary for batch
   */
  private function generateErrorSummary(ImportBatch $batch): array
  {
    $errors = $this->errorRepo->findByBatch($batch, limit: 100);
    
    $summary = [
      'total_errors' => $batch->getFailedRows(),
      'by_type' => [],
      'by_severity' => [],
      'sample_errors' => [],
    ];
    
    foreach ($errors as $error) {
      $type = $error->getErrorType();
      $severity = $error->getSeverity();
      
      $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
      $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
      
      if (count($summary['sample_errors']) < 5) {
        $summary['sample_errors'][] = [
          'row' => $error->getRowNumber(),
          'type' => $type,
          'message' => $error->getErrorMessage(),
        ];
      }
    }
    
    return $summary;
  }

  /**
   * Mark batch as failed
   */
  private function failBatch(ImportBatch $batch, array $errors, ?array $data = null): ServiceResponse
  {
    $batch->setStatus(ImportBatch::STATUS_FAILED);
    $batch->setCompletedAt(new \DateTimeImmutable());
    $batch->setErrorSummary([
      'fatal_errors' => $errors,
      'data' => $data,
    ]);
    $this->batchRepo->save($batch);
    
    return ServiceResponse::failure(
      errors: $errors,
      message: 'Import failed',
      data: ['batch_id' => $batch->getId()]
    );
  }
  
  /**
   * Notify progress callback if provided
   */
  private function notifyProgress(
    ?callable $callback, 
    int $processed, 
    int $total, 
    string $phase
  ): void {
    if ($callback !== null) {
      $callback($processed, $total, $phase);
    }
  }

  /**
   * Rollback an import batch
   * 
   * Deletes all entities created during this import in reverse order.
   * Requires that entities were tracked during import (via batch_id or tags).
   */
  public function rollback(ImportBatch $batch): ServiceResponse
  {
    if (!$batch->canRollback()) {
      return ServiceResponse::failure(
        errors: ['Batch cannot be rolled back in current status: ' . $batch->getStatus()]
      );
    }
    
    $this->logger->info('Rolling back import', [
      'batch_id' => $batch->getId(),
      'status' => $batch->getStatus(),
      'entity_counts' => $batch->getEntityCounts(),
    ]);
    
    try {
      $this->em->beginTransaction();
      
      $entityType = $batch->getMapping()->getEntityType();
      $rollbackCounts = [];
      
      switch ($entityType) {
      case 'order':
        $rollbackCounts['journal_entries'] = $this->rollbackJournalEntries($batch);
        $rollbackCounts['order_items'] = $this->rollbackOrderItems($batch);
        $rollbackCounts['orders'] = $this->rollbackOrders($batch);
        break;
        
      case 'purchase':
        $rollbackCounts['journal_entries'] = $this->rollbackJournalEntries($batch);
        $rollbackCounts['receivings'] = $this->rollbackReceivings($batch);
        $rollbackCounts['purchase_items'] = $this->rollbackPurchaseItems($batch);
        $rollbackCounts['purchases'] = $this->rollbackPurchases($batch);
        break;
        
      case 'item':
        $rollbackCounts['items'] = $this->rollbackItems($batch);
        break;
        
      case 'sellable':
        $rollbackCounts['sellables'] = $this->rollbackSellables($batch);
        break;
        
      default:
        return ServiceResponse::failure(
          errors: ["Rollback not implemented for entity type: {$entityType}"]
        );
      }
      
      $batch->setStatus(ImportBatch::STATUS_ROLLED_BACK);
      $batch->setCompletedAt(new \DateTimeImmutable());
      $batch->setErrorSummary([
        'rollback_counts' => $rollbackCounts,
        'rolled_back_at' => (new \DateTime())->format('c'),
      ]);
            
      $this->em->commit();
      $this->batchRepo->save($batch);
      
      $this->logger->info('Rollback completed', [
        'batch_id' => $batch->getId(),
        'rollback_counts' => $rollbackCounts,
      ]);
      
      return ServiceResponse::success(
        data: [
          'batch_id' => $batch->getId(),
          'rollback_counts' => $rollbackCounts,
        ],
        message: 'Import batch rolled back successfully'
      );
      
    } catch (\Throwable $e) {
      $this->em->rollback();
      
      $this->logger->error('Rollback failed', [
        'batch_id' => $batch->getId(),
        'error' => $e->getMessage(),
      ]);
      
      return ServiceResponse::failure(
        errors: ['Rollback failed: ' . $e->getMessage()]
      );
    }
  }
  
  /**
   * Rollback methods for each entity type
   * These use DBAL for efficiency and to handle detached entity issues
   */
  private function rollbackJournalEntries(ImportBatch $batch): int
  {
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM journal_entry WHERE import_batch_id = ?',
      [$batch->getId()]
    );
  }
    
  private function rollbackOrderItems(ImportBatch $batch): int
  {
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM order_item WHERE order_id IN (SELECT id FROM "order" WHERE import_batch_id = ?)',
      [$batch->getId()]
    );
  }
    
  private function rollbackOrders(ImportBatch $batch): int
  {
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM "order" WHERE import_batch_id = ?',
      [$batch->getId()]
    );
  }
    
  private function rollbackReceivings(ImportBatch $batch): int
  {
    # TODO: Implement something more robust to handle rollbacks of derived data
    return 0;
  }
    
  private function rollbackPurchaseItems(ImportBatch $batch): int
  {
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM purchase_item WHERE purchase_id IN (SELECT id FROM purchase WHERE import_batch_id = ?)',
      [$batch->getId()]
    );
  }
    
  private function rollbackPurchases(ImportBatch $batch): int
  {
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM purchase WHERE import_batch_id = ?',
      [$batch->getId()]
    );
  }
  
  private function rollbackItems(ImportBatch $batch): int
  {
    # TODO: should a DeletePolicy handle logic like this?
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM item WHERE import_batch_id = ? AND id NOT IN (
                SELECT DISTINCT item_id FROM stock_target WHERE item_id IS NOT NULL
                UNION
                SELECT DISTINCT item_id FROM recipe_component WHERE item_id IS NOT NULL
            )',
      [$batch->getId()]
    );
  }
    
  private function rollbackSellables(ImportBatch $batch): int
  {
    # TODO: should a DeletePolicy handle logic like this?
    # Only delete sellables not referenced in orders
    $conn = $this->em->getConnection();
    return $conn->executeStatement(
      'DELETE FROM sellable WHERE import_batch_id = ? AND id NOT IN (
                SELECT DISTINCT sellable_id FROM order_item WHERE sellable_id IS NOT NULL
            )',
      [$batch->getId()]
        );
  }
  
  /**
   * Get import status/progress
   */
  public function getStatus(ImportBatch|int $batch): ServiceResponse
  {
    $batch = $batch instanceof ImportBatch 
      ? $batch 
      : $this->batchRepo->find($batch);
    
    if (!$batch) {
      return ServiceResponse::failure(errors: ['Batch not found']);
    }
    
    return ServiceResponse::success(
      data: [
        'batch_id' => $batch->getId(),
        'name' => $batch->getName(),
        'status' => $batch->getStatus(),
        'total_rows' => $batch->getTotalRows(),
        'processed_rows' => $batch->getProcessedRows(),
        'successful_rows' => $batch->getSuccessfulRows(),
        'failed_rows' => $batch->getFailedRows(),
        'progress_percent' => $batch->getProgressPercent(),
        'entity_counts' => $batch->getEntityCounts(),
        'error_summary' => $batch->getErrorSummary(),
        'started_at' => $batch->getStartedAt()?->format('c'),
        'completed_at' => $batch->getCompletedAt()?->format('c'),
        'can_rollback' => $batch->canRollback(),
      ]
    );
  }
  
  /**
   * Preview import without persisting
   */
  public function preview(
    string $filepath,
    ImportMapping $mapping,
    int $maxRows = 10
  ): ServiceResponse {
    $parseResult = $this->parseFile($filepath);
    if ($parseResult->isFailure()) {
      return $parseResult;
    }
    
    $rows = array_slice($parseResult->data['rows'], 0, $maxRows);
    $headers = $parseResult->data['headers'];
    
    $validationResult = $this->validator->validateBatch($rows, $mapping, ['headers' => $headers]);
        
    $preview = [];
    foreach ($rows as $row) {
      $preview[] = [
        'row_number' => $row['_row_number'],
        'raw_data' => $this->sanitizeRowData($row),
        'transformed' => $this->transactionImporter->transform($row, $mapping),
        'valid' => !isset($validationResult->data['row_errors'][$row['_row_number']]),
      ];
    }
    
    return ServiceResponse::success(
      data: [
        'headers' => $headers,
        'total_rows' => $parseResult->data['row_count'],
        'preview_rows' => $preview,
        'mapping' => [
          'entity_type' => $mapping->getEntityType(),
          'field_mappings' => $mapping->getFieldMappings(),
        ],
        'validation_summary' => [
          'valid' => $validationResult->isSuccess(),
          'error_count' => count($validationResult->data['row_errors'] ?? []),
        ],
      ]
    );
  }
  
  /**
   * Store uploaded file for import processing
   * 
   * STOPGAP IMPLEMENTATION - stores file to var/uploads with unique filename
   * 
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
   * @return string Stored filename (not full path - just the filename)
   * @throws \RuntimeException If file storage fails
   */
  public function storeUploadedFile($file): string
  {
    $originalFilename = $file->getClientOriginalName();
    $fileSize = $file->getSize();
    $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);

    $safeFilename = transliterator_transliterate(
      'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
      $baseFilename
    );
    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

    # TODO: something better than this :)
    $uploadDirectory = __DIR__ . '/../../../var/uploads';
    
    if (!is_dir($uploadDirectory)) {
      mkdir($uploadDirectory, 0775, true);
    }
    
    try {
      $file->move($uploadDirectory, $newFilename);
      
      $this->logger->info('File uploaded successfully', [
        'original_filename' => $originalFilename,
        'stored_filename' => $newFilename,
        'size' => $fileSize,
      ]);
      
      return $newFilename;
      
    } catch (\Exception $e) {
      $this->logger->error('File upload failed', [
        'original_filename' => $originalFilename,
        'error' => $e->getMessage(),
      ]);
      
      throw new \RuntimeException('Failed to store uploaded file: ' . $e->getMessage());
    }
  }
  
  /**
   * Extract headers from stored file
   * 
   * STOPGAP IMPLEMENTATION - parses file and returns first row as headers
   * 
   * @param string $filename Stored filename (not full path)
   * @return array Array of header strings
   * @throws \RuntimeException If file cannot be read or parsed
   */
  public function extractHeaders(string $filename): array
  {
    $filepath = __DIR__ . '/../../../var/uploads/' . $filename;
    
    if (!file_exists($filepath)) {
      throw new \RuntimeException("File not found: {$filename}");
    }
    
    $parseResult = $this->parseFile($filepath);
    
    if ($parseResult->isFailure()) {
      throw new \RuntimeException(
        'Failed to extract headers: ' . implode(', ', $parseResult->errors)
      );
    }
    
    $headers = $parseResult->data['headers'] ?? [];
    
    $this->logger->info('Headers extracted from file', [
      'filename' => $filename,
      'header_count' => count($headers),
      'headers' => $headers,
    ]);
    
    return $headers;
  }
  
  /**
   * Validate and preview data without storing
   * 
   * This method is called by the controller during the validation step.
   * It parses the file, validates rows, and returns preview data with errors.
   * 
   * @param string $filename Stored filename (not full path)
   * @param ImportMapping $mapping Field mapping configuration
   * @param int $previewRows Number of rows to preview (default 50)
   * @return array Validation results with preview data
   * @throws \RuntimeException If file cannot be read
   */
  public function validatePreview(
    string $filename, 
    ImportMapping $mapping, 
    int $previewRows = 50
  ): array {
    $filepath = __DIR__ . '/../../../var/uploads/' . $filename;
    
    if (!file_exists($filepath)) {
      throw new \RuntimeException("File not found: {$filename}");
    }
    
    $parseResult = $this->parseFile($filepath);
    if ($parseResult->isFailure()) {
      throw new \RuntimeException(
        'Failed to parse file: ' . implode(', ', $parseResult->errors)
      );
    }
    
    $rows = $parseResult->data['rows'];
    $headers = $parseResult->data['headers'];
    $totalRows = count($rows);
    
    $validationResult = $this->validator->validateBatch($rows, $mapping, ['headers' => $headers]);
    
    $rowErrors = $validationResult->data['row_errors'] ?? [];
    $criticalErrors = 0;
    $warnings = 0;
    
    foreach ($rowErrors as $errors) {
      foreach ($errors as $error) {
        if (($error['severity'] ?? 'error') === ImportError::SEVERITY_ERROR) {
          $criticalErrors++;
        } else {
          $warnings++;
        }
      }
    }
    
    $preview = [];
    foreach (array_slice($rows, 0, $previewRows) as $row) {
      $rowNumber = $row['_row_number'];
      $preview[] = [
        'row_number' => $rowNumber,
        'data' => array_filter($row, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY),
        'errors' => $rowErrors[$rowNumber] ?? [],
        'has_errors' => isset($rowErrors[$rowNumber]),
      ];
    }
    
    return [
      'total_rows' => $totalRows,
      'preview_rows' => $preview,
      'critical_errors' => $criticalErrors,
      'warnings' => $warnings,
      'valid_rows' => $totalRows - $criticalErrors,
      'validation_passed' => $criticalErrors === 0,
      'headers' => $headers,
    ];
  }
  
  /**
   * Start import batch and begin async processing
   * 
   * This creates the ImportBatch entity and returns it for tracking.
   * The actual import processing should be done asynchronously.
   * 
   * @param string $filename Stored filename (not full path)
   * @param ImportMapping $mapping Field mapping configuration
   * @param string|null $importName Optional name for the import batch
   * @return ImportBatch The created batch entity
   * @throws \RuntimeException If file cannot be accessed
   */
  public function startImport(
    string $filename,
    ImportMapping $mapping,
    ?string $importName = null
  ): ImportBatch {
    $filepath = __DIR__ . '/../../../var/uploads/' . $filename;
    
    if (!file_exists($filepath)) {
      throw new \RuntimeException("File not found: {$filename}");
    }
    
    $batch = new ImportBatch();
    $batch->setName($importName ?? ('Import ' . date('Y-m-d H:i:s')));
    $batch->setMapping($mapping);
    $batch->setStatus(ImportBatch::STATUS_PENDING);
    $batch->setCreatedAt(new \DateTimeImmutable());
    
    $batch->setErrorSummary(['filepath' => $filepath]);
    
    $this->batchRepo->save($batch);
    
    $this->logger->info('Import batch created', [
      'batch_id' => $batch->getId(),
      'filename' => $filename,
      'mapping_id' => $mapping->getId(),
      'name' => $batch->getName(),
    ]);
    
    # TODO: Trigger async job to process the import
    # For now, we'll process synchronously
    $this->importFromFile($filepath, $mapping, [
      'created_by' => null, # TODO: Get from security context
    ]);
    
    return $batch;
  }
}
