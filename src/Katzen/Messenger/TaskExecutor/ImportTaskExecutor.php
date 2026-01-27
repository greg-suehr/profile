<?php

namespace App\Katzen\Messenger\TaskExecutor;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportError;
use App\Katzen\Repository\Import\ImportBatchRepository;
use App\Katzen\Service\Import\DataImportService;
use App\Katzen\Service\Import\EntityMap;
use App\Katzen\Service\Import\Extractor\CatalogExtractor;
use App\Katzen\Service\Import\Extractor\CustomerExtractor;
use App\Katzen\Service\Import\Extractor\LocationExtractor;
use App\Katzen\Service\Import\ExtractorRunner;
use App\Katzen\Service\Import\TransactionImportService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Import Task Executor - Handles async import processing
 * 
 * - process_multi_entity_import: Process a multi-entity import batch
 * - process_single_import: Process a single-entity import batch
 */
final class ImportTaskExecutor implements AsyncTaskExecutorInterface
{
  private const SUPPORTED_TASKS = [
    'process_multi_entity_import',
    'process_single_import',
  ];
    
  public function __construct(
    private EntityManagerInterface $em,
    private ImportBatchRepository $batchRepo,
    private DataImportService $importService,
    private TransactionImportService $transactionImporter,
    private LoggerInterface $logger,
    # TODO: replace with tagged service locator
    private CatalogExtractor $catalogExtractor,
    private LocationExtractor $locationExtractor,
    private ?CustomerExtractor $customerExtractor = null,
  ) {}
  
  public function supports(string $taskType): bool
  {
    return in_array($taskType, self::SUPPORTED_TASKS, true);
  }
    
  /**
   * Execute an import task
   * 
   * @param array{
   *   batch_id: int,
   *   config?: array,
   * } $payload
   */
  public function execute(string $taskType, array $payload, array $options = []): void
  {
    if (!isset($payload['batch_id'])) {
      throw new UnrecoverableMessageHandlingException('Missing required key: batch_id');
    }
    
    $batchId = (int) $payload['batch_id'];
    
    match ($taskType) {
      'process_multi_entity_import' => $this->processMultiEntityImport($batchId, $payload),
      'process_single_import' => $this->processSingleImport($batchId, $payload),
      default => throw new UnrecoverableMessageHandlingException("Unknown task type: {$taskType}"),
    };
  }
    
  /**
   * Process a multi-entity import batch
   */
  private function processMultiEntityImport(int $batchId, array $payload): void
  {
    $batch = $this->batchRepo->find($batchId);

    if (!$batch) {
      throw new UnrecoverableMessageHandlingException("Batch not found: {$batchId}");
    }
    
    if (!$batch->isPending()) {
      $this->logger->warning('Batch already processed or processing', [
        'batch_id' => $batchId,
        'status' => $batch->getStatus(),
      ]);
      return;
    }
    
    $this->logger->info('Starting multi-entity import', [
      'batch_id' => $batchId,
      'total_rows' => $batch->getTotalRows(),
    ]);
    
    try {
      $batch->setStatus(ImportBatch::STATUS_PROCESSING);
      $batch->setStartedAt(new \DateTimeImmutable());
      $this->batchRepo->save($batch);
      
      $config = $payload['config'] ?? $this->loadConfigFromBatch($batch);
      
      if (!$config) {
        throw new \RuntimeException('Import configuration not found');
      }
      
      $filePath = $config['file_path'] ?? $batch->getSourceFilePath();
      if (!$filePath || !file_exists($filePath)) {
        throw new \RuntimeException("Source file not found: {$filePath}");
      }
      
      $parseResult = $this->importService->parseFile($filePath);
      if ($parseResult->isFailure()) {
        throw new \RuntimeException('Failed to parse file: ' . implode(', ', $parseResult->errors));
      }
      
      $rows = $parseResult->data['rows'];
      $headers = $parseResult->data['headers'];
      $enabledEntities = $config['enabled_entities'] ?? [];
      $entityMappings = $config['entity_mappings'] ?? [];
      
      $batch->setTotalRows(count($rows));
      
      $runner = $this->buildExtractorRunner();
            
      $this->logger->info('Phase 1: Extracting master data', [
        'batch_id' => $batchId,
        'enabled_entities' => $enabledEntities,
      ]);
      
      $entityMap = new EntityMap();
      $entityCounts = [];

      foreach ($this->getExtractionOrder($enabledEntities) as $entityType) {
        if (!in_array($entityType, $enabledEntities)) {
          continue;
        }
        
        $mapping = $batch->getMapping();
        if (isset($entityMappings[$entityType])) {
          $mapping = $this->createMappingForEntity($entityType, $entityMappings[$entityType]);
        }
        
        $result = $this->extractEntityType(
          $entityType, 
          $rows, 
          $headers, 
          $mapping,
          $batch,
          $entityMap
        );

        if ($result) {
          $entityCounts[$entityType] = $result['count'] ?? 0;
          
          if (isset($result['entity_map'])) {
            $entityMap->merge($result['entity_map']);
          }
        }
        
        $this->logger->info("Extracted {$entityType}", [
          'count' => $entityCounts[$entityType] ?? 0,
        ]);
      }
      
      if (array_intersect(['order', 'order_item', 'purchase'], $enabledEntities)) {
        $this->logger->info('Phase 2: Processing transactions', ['batch_id' => $batchId]);
        
        $transactionResult = $this->transactionImporter->processGroupedTransactions(
          $rows,
          $batch->getMapping(),
          $batchId,
          $entityMap,
          [
            'customer_strategy' => $config['customer_strategy'] ?? 'match',
            'order_status' => 'completed',
          ]
        );
        
        if (isset($transactionResult->data['entity_counts'])) {
          $entityCounts = array_merge($entityCounts, $transactionResult->data['entity_counts']);
        }
        
        $batch->setSuccessfulRows($transactionResult->data['details']['orders_created'] ?? 0);
        $batch->setProcessedRows($batch->getTotalRows());
      } else {
        $batch->setProcessedRows(count($rows));
        $batch->setSuccessfulRows(count($rows));
      }

      $this->em->flush();

      $batch->setEntityCounts($entityCounts);
      $batch->setStatus(ImportBatch::STATUS_COMPLETED);
      $batch->setCompletedAt(new \DateTime());      
      $batch->setErrorSummary($this->buildErrorSummary($batch));

      $this->batchRepo->save($batch);

      $this->logger->info('Multi-entity import completed', [
        'batch_id' => $batchId,
        'entity_counts' => $entityCounts,
        'successful_rows' => $batch->getSuccessfulRows(),
      ]);
      
    } catch (\Throwable $e) {
      $this->logger->error('Multi-entity import failed', [
        'batch_id' => $batchId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      
      $batch->setStatus(ImportBatch::STATUS_FAILED);
      $batch->setCompletedAt(new \DateTime());
      $batch->setErrorSummary([
        'fatal_error' => $e->getMessage(),
        'at_row' => $batch->getProcessedRows(),
      ]);
      $this->batchRepo->save($batch);
      
      if ($this->isTransientError($e)) {
        throw new RecoverableMessageHandlingException(
          "Transient error processing batch {$batchId}: {$e->getMessage()}"
        );
      }
      
      throw new UnrecoverableMessageHandlingException(
        "Fatal error processing batch {$batchId}: {$e->getMessage()}"
      );
    }
  }
    
  /**
   * Process a single-entity import
   */
  private function processSingleImport(int $batchId, array $payload): void
  {
    $batch = $this->batchRepo->find($batchId);
        
    if (!$batch) {
      throw new UnrecoverableMessageHandlingException("Batch not found: {$batchId}");
    }
    
    $filePath = $batch->getSourceFilePath();
    if (!$filePath || !file_exists($filePath)) {
      throw new UnrecoverableMessageHandlingException("Source file not found");
    }
        
    $mapping = $batch->getMapping();
    if (!$mapping) {
      throw new UnrecoverableMessageHandlingException("No mapping configured for batch");
    }
        
    $result = $this->importService->importFromFile($filePath, $mapping, [
      'batch_id' => $batchId,
    ]);
    
    if ($result->isFailure()) {
      throw new UnrecoverableMessageHandlingException(
        'Import failed: ' . implode(', ', $result->errors)
        );
    }
  }
    
  /**
   * Build extractor runner with all available extractors
   */
  private function buildExtractorRunner(): ExtractorRunner
  {
    $runner = new ExtractorRunner($this->logger);
        
    $runner->addExtractor($this->locationExtractor);
    $runner->addExtractor($this->catalogExtractor);
    
    if ($this->customerExtractor) {
      $runner->addExtractor($this->customerExtractor);
    }
    
    return $runner;
  }
    
  /**
   * Get optimal extraction order based on dependencies
   */
  private function getExtractionOrder(array $enabledEntities): array
  {
    $order = [
      'stock_location',
      'vendor',
      'customer',
      'item',
      'sellable',
      'sellable_variant',
      'order',
      'order_item',
      'purchase',
      'purchase_item',
    ];
    
    return array_values(array_intersect($order, $enabledEntities));
  }
  
  /**
   * Extract entities of a specific type
   */
  private function extractEntityType(
    string $entityType,
    array $rows,
    array $headers,
    $mapping,
    ImportBatch $batch,
    EntityMap $entityMap
  ): ?array {
    $extractor = match ($entityType) {
      'stock_location' => $this->locationExtractor,
      'sellable', 'sellable_variant', 'item' => $this->catalogExtractor,
      'customer' => $this->customerExtractor,
      default => null,
    };
    
    if (!$extractor) {
      return null;
    }
    
    $confidence = $extractor->detect($headers, array_slice($rows, 0, 100));
    
    if ($confidence < 0.2) {
      $this->logger->debug('Extractor confidence too low', [
        'entity_type' => $entityType,
        'confidence' => $confidence,
      ]);
      return null;
    }
    
    $extractionResult = $extractor->extract($rows, $headers, $mapping);
        
    $createResult = $extractor->createEntities(
      $extractionResult->records,
      $batch,
    );
    
    return [
      'count' => $extractionResult->getTotalRecordCount(),
      'entity_map' => $createResult->data['entity_map'] ?? null,
    ];
  }
  
  /**
   * Load configuration from batch metadata
   */
  private function loadConfigFromBatch(ImportBatch $batch): ?array
  {
    $metadata = $batch->getMetadata();
    if ($metadata && isset($metadata['enabled_entities'])) {
        return $metadata;
    }
    
    $errorSummary = $batch->getErrorSummary();
    if (isset($errorSummary['filepath'])) {
        return [
            'file_path' => $errorSummary['filepath'],
            'enabled_entities' => [$batch->getMapping()->getEntityType()],
        ];
    }
    
    return null;
  }
    
  /**
   * Create a mapping for a specific entity type from config
   */
  private function createMappingForEntity(string $entityType, array $fieldMappings): object
  {
    $mapping = new \App\Katzen\Entity\Import\ImportMapping();
    $mapping->setEntityType($entityType);
    $mapping->setFieldMappings($fieldMappings);
    return $mapping;
  }
  
  /**
   * Build error summary for completed batch
   */
  private function buildErrorSummary(ImportBatch $batch): array
  {
    $errors = $batch->getErrors();
    $summary = [
      'total_errors' => $errors->count(),
      'by_severity' => [],
      'by_type' => [],
    ];
    
    foreach ($errors as $error) {
      $severity = $error->getSeverity();
      $type = $error->getErrorType() ?? 'unknown';
      
      $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
      $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
    }
    
    return $summary;
  }
    
  /**
   * Determine if an error is transient (worth retrying)
   */
  private function isTransientError(\Throwable $e): bool
  {
    $message = strtolower($e->getMessage());
        
    $transientPatterns = [
      'deadlock',
      'lock wait timeout',
      'connection refused',
      'connection reset',
      'too many connections',
      'server has gone away',
    ];
    
    foreach ($transientPatterns as $pattern) {
      if (str_contains($message, $pattern)) {
        return true;
      }
    }
    
    return false;
  }
}
