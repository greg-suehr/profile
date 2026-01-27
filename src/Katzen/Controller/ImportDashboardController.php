<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\ShowPage\PageAction;
use App\Katzen\Component\ShowPage\PageSection;
use App\Katzen\Component\ShowPage\ShowPage;
use App\Katzen\Component\ShowPage\ShowPageFooter;
use App\Katzen\Component\ShowPage\ShowPageHeader;
use App\Katzen\Component\TableView\TableAction;
use App\Katzen\Component\TableView\TableField;
use App\Katzen\Component\TableView\TableRow;
use App\Katzen\Component\TableView\TableView;
use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Form\ImportMappingType;
use App\Katzen\Form\ImportUploadType;
use App\Katzen\Messenger\TaskExecutor\ImportTaskExecutor;
use App\Katzen\Repository\Import\ImportBatchRepository;
use App\Katzen\Repository\Import\ImportErrorRepository;
use App\Katzen\Repository\Import\ImportMappingRepository;
use App\Katzen\Repository\Import\ImportMappingLearningRepository;
use App\Katzen\Service\Import\DataExtractor;
use App\Katzen\Service\Import\DataImportService;
use App\Katzen\Service\Import\Extractor\CatalogExtractor;
use App\Katzen\Service\Import\Extractor\LocationExtractor;
use App\Katzen\Service\Import\ImportMappingService;
use App\Katzen\Service\Import\MultiEntityMappingResult;
use App\Katzen\Service\Utility\DashboardContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Import Dashboard Controller
 * 
 * Provides visibility into the import system:
 * - Dashboard with stats and recent batches
 * - Batch detail pages with error analysis
 * - Mapping template management
 * - Multi-entity import wizard (Upload > Plan > Validate > Execute)
 * - Progress tracking endpoints
 */
#[Route('/import', name: 'import_')]
final class ImportDashboardController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private ImportBatchRepository $batchRepo,
    private ImportMappingRepository $mappingRepo,
    private ImportErrorRepository $errorRepo,
    private ImportMappingLearningRepository $learningRepo,
    private DataImportService $importService,
    private ImportMappingService $mappingService,
    private EntityManagerInterface $em,
    private CsrfTokenManagerInterface $csrfTokenManager,
    private ImportTaskExecutor $tasks,
    # TODO: Use tagged service locator for cleaner DI
    private CatalogExtractor $catalogExtractor,    
    private LocationExtractor $locationExtractor,
  ) {}

  // ========================================================================
  // Dashboard & Batch Management
  // ========================================================================

  /**
   * Import Dashboard - Overview of import system health
   */
  #[Route('/', name: 'dashboard')]
  #[DashboardLayout('supply', 'import', 'import-dashboard')]
  public function dashboard(Request $request): Response
  {
    $stats = $this->batchRepo->getStatistics();
    $statusCounts = $this->batchRepo->countByStatus();
    $recentBatches = $this->batchRepo->findRecent(10);
    $activeBatches = $this->batchRepo->findActive();
    $mappingCounts = $this->mappingRepo->countByEntityType();
    
    $successRate = $stats['total_rows'] > 0 
      ? round(($stats['successful_rows'] / $stats['total_rows']) * 100, 1)
      : 0;
    
    $batchTable = $this->buildBatchTable($recentBatches);
    
    return $this->render('katzen/import/dashboard.html.twig', $this->dashboardContext->with([
      'stats' => [
        'total_batches' => $stats['total_batches'],
        'total_rows' => number_format($stats['total_rows']),
        'successful_rows' => number_format($stats['successful_rows']),
        'failed_rows' => number_format($stats['failed_rows']),
        'success_rate' => $successRate,
        'active_count' => count($activeBatches),
        'pending_count' => $statusCounts[ImportBatch::STATUS_PENDING] ?? 0,
        'processing_count' => $statusCounts[ImportBatch::STATUS_PROCESSING] ?? 0,
      ],
      'status_counts' => $statusCounts,
      'mapping_counts' => $mappingCounts,
      'active_batches' => $activeBatches,
      'table' => $batchTable,
    ]));
  }
  
  /**
   * List all import batches with filtering
   */
  #[Route('/batches', name: 'batches')]
  #[DashboardLayout('supply', 'import', 'import-batches')]
  public function batches(Request $request): Response
  {
    $status = $request->query->get('status', 'all');
      
    $batches = $status === 'all'
      ? $this->batchRepo->findRecent(100)
      : $this->batchRepo->findByStatus($status);
    
    $table = $this->buildBatchTable($batches, showAll: true);
    
    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([      
      'table' => $table,
      'bulkRoute' => 'import_dashboard_batches_bulk',
      'csrfSlug' => 'import_dashboard_batches_bulk',
    ]));
  }

  #[Route('/bulk', name: 'dashboard_batches_bulk', methods: ['POST'])]
  public function batchesBulk(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('import_dashboard_batches_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids = array_map('intval', $payload['ids'] ?? []);

    if (empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'No import batches selected'], 400);
    }

    $batches = $this->batchRepo->findBy(['id' => $ids]);
    $count = count($batches);

    switch ($action) {
    case 'delete':
      foreach ($batches as $b) {
        $this->batchRepo->remove($b);
      }
      $this->batchRepo->flush();
      return $this->json(['ok' => true, 'message' => "$count import batch(es) deleted"]);
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
  }  

  /**
   * Show import batch details
   */
  #[Route('/batch/{id}', name: 'batch_show')]
  #[DashboardLayout('supply', 'import', 'import-batch-detail')]
  public function batchShow(int $id): Response
  {
    $batch = $this->batchRepo->find($id);
      
    if (!$batch) {
      throw $this->createNotFoundException('Import batch not found');
    }
    
    $errorSummary = $this->errorRepo->getComprehensiveSummary($batch);
    $problematicRows = $this->errorRepo->getProblematicRows($batch, 5);
    $uniqueErrors = $this->errorRepo->getUniqueErrorMessages($batch, 10);
    
    $page = $this->buildBatchShowPage($batch);
    
    return $this->render('katzen/import/batch_show.html.twig', $this->dashboardContext->with([
      'batch' => $batch,
      'page' => $page,
      'error_summary' => $errorSummary,
      'problematic_rows' => $problematicRows,
      'unique_errors' => $uniqueErrors,
    ]));
  }

  /**
   * View errors for a batch
   */
  #[Route('/batch/{id}/errors', name: 'batch_errors')]
  #[DashboardLayout('supply', 'import', 'import-batch-errors')]
  public function batchErrors(int $id, Request $request): Response
  {
    $batch = $this->batchRepo->find($id);
      
    if (!$batch) {
      throw $this->createNotFoundException('Import batch not found');
    }
    
    $page = $request->query->getInt('page', 1);
    $errors = $this->errorRepo->findByBatchPaginated($batch, $page, 50);
    $totalErrors = $this->errorRepo->countByBatch($batch);
    $summary = $this->errorRepo->getErrorSummaryByType($batch);
    
    $table = $this->buildErrorTable($errors);
    
    return $this->render('katzen/import/batch_errors.html.twig', $this->dashboardContext->with([
      'batch' => $batch,
      'table' => $table,
      'summary' => $summary,
      'total_errors' => $totalErrors,
      'current_page' => $page,
      'total_pages' => ceil($totalErrors / 50),
    ]));
  }

  /**
   * Get batch progress (AJAX endpoint)
   */
  #[Route('/batch/{id}/progress', name: 'batch_progress', methods: ['GET'])]
  public function batchProgress(int $id): JsonResponse
  {
    $batch = $this->batchRepo->find($id);
      
    if (!$batch) {
      return $this->json(['error' => 'Batch not found'], 404);
    }

    return $this->json([
      'id' => $batch->getId(),
      'status' => $batch->getStatus(),
      'total_rows' => $batch->getTotalRows(),
      'processed_rows' => $batch->getProcessedRows(),
      'successful_rows' => $batch->getSuccessfulRows(),
      'failed_rows' => $batch->getFailedRows(),
      'progress_percent' => $batch->getProgressPercent(),
      'is_complete' => in_array($batch->getStatus(), [
        ImportBatch::STATUS_COMPLETED,
        ImportBatch::STATUS_FAILED,
        ImportBatch::STATUS_ROLLED_BACK,
      ]),
    ]);
  }

  /**
   * Rollback an import batch
   */
  #[Route('/batch/{id}/rollback', name: 'batch_rollback', methods: ['POST'])]
  public function batchRollback(int $id): Response
  {
    $batch = $this->batchRepo->find($id);
      
    if (!$batch) {
      throw $this->createNotFoundException('Import batch not found');
    }
    
    if (!$batch->canRollback()) {
      $this->addFlash('error', 'This import cannot be rolled back.');
      return $this->redirectToRoute('import_batch_show', ['id' => $id]);
    }
    
    try {
      $this->importService->rollbackBatch($batch);
      $this->addFlash('success', 'Import has been successfully rolled back.');
    } catch (\Exception $e) {
      $this->addFlash('error', 'Rollback failed: ' . $e->getMessage());
    }
    
    return $this->redirectToRoute('import_batch_show', ['id' => $id]);
  }

  // ========================================================================
  // Mapping Management
  // ========================================================================

  /**
   * List import mappings
   */
  #[Route('/mappings', name: 'mappings')]
  #[DashboardLayout('supply', 'import', 'import-mappings')]
  public function mappings(Request $request): Response
  {
    $entityType = $request->query->get('type');
      
    $mappings = $entityType
      ? $this->mappingRepo->findByEntityType($entityType)
      : $this->mappingRepo->findRecent(50);
    
    $table = $this->buildMappingTable($mappings);
    $entityTypes = $this->mappingRepo->findDistinctEntityTypes();
    
    return $this->render('katzen/import/mappings.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'entity_types' => $entityTypes,
      'current_type' => $entityType,
    ]));
  }

  /**
   * Show mapping details
   */
  #[Route('/mapping/{id}', name: 'mapping_show')]
  #[DashboardLayout('supply', 'import', 'import-mapping-detail')]
  public function mappingShow(int $id): Response
  {
    $mapping = $this->mappingRepo->find($id);
      
    if (!$mapping) {
      throw $this->createNotFoundException('Import mapping not found');
    }
    
    $batches = $this->batchRepo->findByMapping($id);
    $usageCount = count($batches);
    $lastUsed = $batches[0] ?? null;
    
    $page = $this->buildMappingShowPage($mapping, $usageCount, $lastUsed);
    
    return $this->render('katzen/import/mapping_show.html.twig', $this->dashboardContext->with([
      'mapping' => $mapping,
      'page' => $page,
      'field_mappings' => $mapping->getFieldMappings(),
      'transformation_rules' => $mapping->getTransformationRules() ?? [],
      'validation_rules' => $mapping->getValidationRules() ?? [],
      'default_values' => $mapping->getDefaultValues() ?? [],
    ]));
  }

  /**
   * Clone an existing mapping
   */
  #[Route('/mapping/{id}/clone', name: 'mapping_clone', methods: ['POST'])]
  public function mappingClone(int $id): Response
  {
    $original = $this->mappingRepo->find($id);
      
    if (!$original) {
      throw $this->createNotFoundException('Import mapping not found');
    }
    
    try {
      $cloned = $this->mappingService->cloneMapping($original);
      $this->addFlash('success', 'Mapping template cloned successfully.');
      return $this->redirectToRoute('import_mapping_show', ['id' => $cloned->getId()]);
    } catch (\Exception $e) {
      $this->addFlash('error', 'Failed to clone mapping: ' . $e->getMessage());
      return $this->redirectToRoute('import_mapping_show', ['id' => $id]);
    }
  }

  /**
   * View learning statistics
   */
  #[Route('/learning', name: 'learning')]
  #[DashboardLayout('supply', 'import', 'import-learning')]
  public function learning(): Response
  {
    $stats = $this->learningRepo->getStatisticsByEntityType();
    $topMappings = $this->learningRepo->getTopMappings(20);
    $conflicts = $this->learningRepo->findConflictingMappings();
    
    return $this->render('katzen/import/learning.html.twig', $this->dashboardContext->with([
      'stats' => $stats,
      'top_mappings' => $topMappings,
      'conflicts' => $conflicts,
    ]));
  }

  // ========================================================================
  // Import Wizard: Multi-Entity Flow
  // ========================================================================

  /**
   * Import wizard Step 1: Upload file
   * 
   * Handles file upload, parses CSV, and redirects to multi-entity planning.
   */
  #[Route('/upload', name: 'upload')]
  #[DashboardLayout('supply', 'import', 'import-upload')]
  public function upload(Request $request): Response
  {    
    $availableMappings = [];
    foreach ($this->mappingRepo->findAll() as $mapping) {
      $availableMappings[$mapping->getName()] = $mapping->getId();
    }
    
    $form = $this->createForm(ImportUploadType::class, null, [
      'available_mappings' => $availableMappings,
    ]);
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();
      
      /** @var UploadedFile $file */
      $file = $data['file'];
      $importName = $data['name'] ?? $file->getClientOriginalName();
      $existingMappingId = $data['use_existing_mapping'] ?? null;
      
      try {
        $storedFilename = $this->importService->storeUploadedFile($file);
        $headers = $this->importService->extractHeaders($storedFilename);
        
        $filepath = $this->getParameter('kernel.project_dir') . '/src/var/uploads/' . $storedFilename;
        $parseResult = $this->importService->parseFile($filepath);
        
        if (!$parseResult->isSuccess()) {
          $this->addFlash('error', 'Failed to parse file: ' . $parseResult->message);
          return $this->redirectToRoute('import_upload');
        }
        
        $rows = $parseResult->getData()['rows'];
        
        $session = $request->getSession();
        $session->set('import_upload_data', [
          'file_path' => $filepath,
          'file_name' => $importName,
          'stored_filename' => $storedFilename,
          'headers' => $headers,
          'sample_rows' => array_slice($rows, 0, 100),
          'all_rows' => $rows,
          'total_row_count' => count($rows),
        ]);
        
        if ($existingMappingId) {
          $session->set('import_mapping_id', $existingMappingId);
          return $this->redirectToRoute('import_validate');
        }
        
        return $this->redirectToRoute('import_plan');
        
      } catch (\Exception $e) {
        $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
      }
    }
    
    return $this->render('katzen/import/upload.html.twig', $this->dashboardContext->with([
      'form' => $form,
    ]));
  }

  /**
   * Import wizard Step 2: Multi-entity planning
   * 
   * Displays detected entities and allows configuration of what to extract.
   */
  #[Route('/plan', name: 'plan')]
  #[DashboardLayout('supply', 'import', 'import-plan')]
  public function plan(Request $request): Response
  {
    $session = $request->getSession();
    $uploadData = $session->get('import_upload_data');
    
    if (!$uploadData) {
      $this->addFlash('warning', 'Please upload a file first.');
      return $this->redirectToRoute('import_upload');
    }
    
    $headers = $uploadData['headers'];
    $sampleRows = $uploadData['sample_rows'];
    
    if ($request->isMethod('POST')) {
      return $this->handlePlanSubmission($request, $uploadData);
    }
    
    $detectionResponse = $this->mappingService->detectMultiEntityMapping($headers, $sampleRows);
    
    if (!$detectionResponse->isSuccess()) {
      $this->addFlash('warning', 'Could not auto-detect data types: ' . $detectionResponse->message);
      return $this->redirectToRoute('import_configure_mapping');
    }
    
    /** @var MultiEntityMappingResult $detection */
    $detection = $detectionResponse->getData()['detection_result'];
    $detectionData = $detection->toArray();
    $session->set('import_detection', $detectionData);

    $displayData = $detectionData;
    $displayData['strategy_summary'] = $detection->getStrategySummary();
    $displayData['entity_cards'] = $detection->getEntityCards();
    $displayData['entity_mapping_details'] = $detection->getEntityMappingDetails(); 
    $displayData['header_assignments'] = $detection->getHeaderAssignments();

    return $this->render('katzen/import/plan.html.twig', $this->dashboardContext->with([
      'detection' => $displayData,
      'headers' => $headers,
      'sample_rows' => array_slice($sampleRows, 0, 10),
      'file_name' => $uploadData['file_name'] ?? 'Uploaded File',
      'total_rows' => $uploadData['total_row_count'] ?? count($uploadData['all_rows'] ?? []),
      'csrf_token' => $this->generateCsrfToken('import_plan'),
      'show_advanced' => $request->query->getBoolean('advanced', false),
    ]));
  }

  /**
   * Handle plan form submission
   */
  private function handlePlanSubmission(Request $request, array $uploadData): Response
  {
    $payload = $request->request->all();
    
    if (!$this->isCsrfTokenValid('import_plan', $payload['_token'] ?? '')) {
      throw $this->createAccessDeniedException('Invalid CSRF token');
    }
    
    $session = $request->getSession();
    
    $enabledEntities = [];
    foreach ($payload['entities'] ?? [] as $entityType => $config) {
      if (!empty($config['enabled'])) {
        $enabledEntities[] = $entityType;
      }
    }
    
    if (empty($enabledEntities)) {
      $this->addFlash('warning', 'Please select at least one entity type to import.');
      return $this->redirectToRoute('import_plan');
    }
    
    $entityMappings = [];
    foreach ($payload['mappings'] ?? [] as $entityType => $mappings) {
      if (in_array($entityType, $enabledEntities)) {
        $entityMappings[$entityType] = array_filter($mappings, fn($v) => !empty(trim($v)));
      }
    }
    
    $session->set('import_config', [
      'enabled_entities' => $enabledEntities,
      'entity_mappings' => $entityMappings,
      'primary_entity' => $payload['primary_entity'] ?? $enabledEntities[0] ?? null,
      'extraction_strategy' => json_decode($payload['extraction_strategy'] ?? '{}', true),
    ]);
    
    return $this->redirectToRoute('import_validate');
  }

  /**
   * Adjust mapping via AJAX (for inline editing)
   */
  #[Route('/plan/adjust', name: 'adjust_mapping', methods: ['POST'])]
  public function adjustMapping(Request $request): JsonResponse
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    
    if (!$this->isCsrfTokenValid('import_plan', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
    }
    
    $session = $request->getSession();
    $detection = $session->get('import_detection');
    
    if (!$detection) {
      return $this->json(['ok' => false, 'error' => 'No detection data found'], 400);
    }
    
    $action = $payload['action'] ?? null;
    
    switch ($action) {
      case 'toggle_entity':
        $entityType = $payload['entity_type'] ?? null;
        $enabled = $payload['enabled'] ?? false;
        $toggles = $session->get('import_entity_toggles', []);
        $toggles[$entityType] = $enabled;
        $session->set('import_entity_toggles', $toggles);
        return $this->json(['ok' => true, 'entity' => $entityType, 'enabled' => $enabled]);
        
      case 'update_mapping':
        $entityType = $payload['entity_type'] ?? null;
        $column = $payload['column'] ?? null;
        $targetField = $payload['target_field'] ?? null;
        return $this->json(['ok' => true, 'updated' => true]);
        
      default:
        return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
  }

  /**
   * Import wizard Step 3: Validate data
   */
  #[Route('/validate', name: 'validate')]
  #[DashboardLayout('supply', 'import', 'import-validate')]
  public function validate(Request $request): Response
  {
    $session = $request->getSession();
    $uploadData = $session->get('import_upload_data');
    $importConfig = $session->get('import_config');
    
    if ($uploadData && $importConfig) {
      return $this->validateMultiEntity($request, $uploadData, $importConfig);
    }
    
    return $this->validateLegacy($request);
  }

  /**
   * Multi-entity validation
   */
  private function validateMultiEntity(Request $request, array $uploadData, array $importConfig): Response
  {
    $validationResults = [];
    $totalEstimates = [
      'total_rows' => $uploadData['total_row_count'] ?? count($uploadData['all_rows'] ?? []),
      'entities_to_create' => [],
    ];
    
    foreach ($importConfig['enabled_entities'] as $entityType) {
      $mapping = $this->mappingService->createTemporaryMapping(
        $entityType,
        $importConfig['entity_mappings'][$entityType] ?? []
      );
      
      $validationResults[$entityType] = [
        'entity_type' => $entityType,
        'label' => $this->formatEntityLabel($entityType),
        'mapping_completeness' => $this->assessMappingCompleteness($mapping, $entityType),
        'field_count' => count($importConfig['entity_mappings'][$entityType] ?? []),
      ];
      
      $extractor = $this->getExtractorForEntity($entityType);
      if ($extractor) {
        $confidence = $extractor->detect(
          $uploadData['headers'],
          $uploadData['sample_rows']
        );
        
        $sampleResult = $extractor->extract(
          $uploadData['sample_rows'],
          $uploadData['headers'],
          $mapping
        );
        
        $validationResults[$entityType]['confidence'] = $confidence;
        $validationResults[$entityType]['sample_count'] = $sampleResult->getTotalRecordCount();
        $validationResults[$entityType]['estimated_total'] = $this->estimateTotal(
          $sampleResult->getTotalRecordCount(),
          count($uploadData['sample_rows']),
          $totalEstimates['total_rows']
        );
        $validationResults[$entityType]['warnings'] = $sampleResult->warnings;
        
        $totalEstimates['entities_to_create'][$entityType] = 
          $validationResults[$entityType]['estimated_total'];
      }
    }
    
    $canProceed = true;
    foreach ($validationResults as $result) {
      if (!($result['mapping_completeness']['is_complete'] ?? true)) {
        $missingCritical = array_filter(
          $result['mapping_completeness']['missing_required'] ?? [],
          fn($f) => !($f['has_default'] ?? false)
        );
        if (!empty($missingCritical)) {
          $canProceed = false;
        }
      }
    }

    return $this->render('katzen/import/validate.html.twig', $this->dashboardContext->with([
      'is_multi_entity' => true,
      'config' => $importConfig,
      'validation_results' => $validationResults,
      'total_estimates' => $totalEstimates,
      'file_name' => $uploadData['file_name'] ?? 'Uploaded File',
      'can_proceed' => $canProceed,
      'csrf_token' => $this->generateCsrfToken('import_execute'),
    ]));
  }

  /**
   * Legacy single-entity validation (backward compatibility)
   */
  private function validateLegacy(Request $request): Response
  {
    $session = $request->getSession();
    $filename = $session->get('import_file');
    $entityType = $session->get('import_entity_type');
    $mappingId = $session->get('import_mapping_id');
    $fieldMappings = $session->get('import_field_mappings');

    if (!$filename || !$entityType) {
      $this->addFlash('warning', 'Please start the import process from the beginning.');
      return $this->redirectToRoute('import_upload');
    }
    
    if (!$mappingId && !$fieldMappings) {
      $this->addFlash('warning', 'Please configure field mappings first.');
      return $this->redirectToRoute('import_configure_mapping');
    }
    
    try {
      $mapping = $mappingId 
        ? $this->mappingRepo->find($mappingId)
        : $this->mappingService->createTemporaryMapping($entityType, $fieldMappings);

      $validationResults = $this->importService->validatePreview($filename, $mapping, 50);

      return $this->render('katzen/import/validate.html.twig', $this->dashboardContext->with([
        'is_multi_entity' => false,
        'validation_results' => $validationResults,
        'mapping' => $mapping,
        'entity_type' => $entityType,
        'can_proceed' => $validationResults['critical_errors'] === 0,
        'csrf_token' => $this->generateCsrfToken('import_execute'),
      ]));
      
    } catch (\Exception $e) {
      $this->addFlash('error', 'Validation failed: ' . $e->getMessage());
      return $this->redirectToRoute('import_configure_mapping');
    }
  }

  /**
   * Legacy manual mapping configuration (fallback when auto-detection fails)
   */
  #[Route('/configure-mapping', name: 'configure_mapping')]
  #[DashboardLayout('supply', 'import', 'import-mapping')]
  public function configureMapping(Request $request): Response
  {
    $session = $request->getSession();
    
    $uploadData = $session->get('import_upload_data');
    if ($uploadData) {
      $headers = $uploadData['headers'];
      $entityType = 'item';
    } else {
      $headers = $session->get('import_headers');
      $entityType = $session->get('import_entity_type');
    }
    
    $suggestedMappings = $session->get('import_suggested_mappings', []);
    
    if (!$headers) {
      $this->addFlash('warning', 'Please upload a file first.');
      return $this->redirectToRoute('import_upload');
    }
    
    $form = $this->createForm(ImportMappingType::class, null, [
      'headers' => $headers,
      'suggested' => $suggestedMappings,
      'entity_type' => $entityType,
    ]);
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();
      
      if ($data['save_as_template'] ?? false) {
        try {
          $mapping = $this->mappingService->createMappingFromFormData(
            $entityType,
            $data,
            $headers
          );
          $session->set('import_mapping_id', $mapping->getId());
          $this->addFlash('success', 'Mapping template saved successfully.');
        } catch (\Exception $e) {
          $this->addFlash('error', 'Failed to save mapping template: ' . $e->getMessage());
        }
      } else {
        $session->set('import_field_mappings', $data);
      }
      
      return $this->redirectToRoute('import_validate');
    }
    
    return $this->render('katzen/import/configure_mapping.html.twig', $this->dashboardContext->with([
      'form' => $form,
      'headers' => $headers,
      'suggested' => $suggestedMappings,
      'entity_type' => $entityType,
    ]));
  }

  /**
   * Import wizard Step 4: Execute import
   */
  #[Route('/execute', name: 'execute', methods: ['POST'])]
  public function execute(Request $request): Response
  {
    if (!$this->isCsrfTokenValid('import_execute', $request->request->get('_token'))) {
      throw $this->createAccessDeniedException('Invalid CSRF token');
    }
    
    $session = $request->getSession();
    $uploadData = $session->get('import_upload_data');
    $importConfig = $session->get('import_config');
    
    if ($uploadData && $importConfig) {
      return $this->executeMultiEntity($request, $uploadData, $importConfig);
    }
    
    return $this->executeLegacy($request);
  }

  /**
   * Execute multi-entity import
   */
  private function executeMultiEntity(Request $request, array $uploadData, array $importConfig): Response
  {
    $session = $request->getSession();
    
    $batch = new ImportBatch();
    $batch->setName(sprintf(
      'Multi-Entity Import: %s (%s)',
      implode(', ', array_map(fn($e) => $this->formatEntityLabel($e), $importConfig['enabled_entities'])),
      $uploadData['file_name'] ?? 'Unknown'
    ));
    $batch->setStatus(ImportBatch::STATUS_PENDING);
    $batch->setTotalRows($uploadData['total_row_count'] ?? count($uploadData['all_rows'] ?? []));
    
    $mapping = $this->mappingService->createTemporaryMapping("multi", []);
    $batch->setMapping($mapping);
    $batch->setMetadata([
      'type' => 'multi',
      'file_path' => $uploadData['file_path'],
      'enabled_entities' => $importConfig['enabled_entities'],
      'extraction_strategy' => $importConfig['extraction_strategy'],
      'entity_mappings' => $importConfig['entity_mappings'],
    ]);
    
    $this->em->persist($batch);
    $this->em->flush();
    
    # TODO: Queue the import job for async processing
    $this->tasks->execute('process_multi_entity_import', ['batch_id' => $batch->getId()]);
    
    $this->addFlash('success', 'Import started! You can track progress below.');
    
    $this->clearImportSession($session);
    
    return $this->redirectToRoute('import_batch_show', ['id' => $batch->getId()]);
  }

  /**
   * Execute legacy single-entity import
   */
  private function executeLegacy(Request $request): Response
  {
    $session = $request->getSession();
    $filename = $session->get('import_file');
    $entityType = $session->get('import_entity_type');
    $importName = $session->get('import_name');
    $mappingId = $session->get('import_mapping_id');
    $fieldMappings = $session->get('import_field_mappings');
    
    if (!$filename || !$entityType) {
      $this->addFlash('error', 'Missing import data. Please start over.');
      return $this->redirectToRoute('import_upload');
    }
    
    try {
      $mapping = $mappingId 
        ? $this->mappingRepo->find($mappingId)
        : $this->mappingService->createTemporaryMapping($entityType, $fieldMappings);
      
      $batch = $this->importService->startImport(
        $filename,
        $mapping,
        $importName
      );
      
      $this->clearImportSession($session);
      
      return $this->redirectToRoute('import_batch_show', ['id' => $batch->getId()]);
      
    } catch (\Exception $e) {
      $this->addFlash('error', 'Import failed: ' . $e->getMessage());
      return $this->redirectToRoute('import_validate');
    }
  }

  /**
   * Clear all import-related session data
   */
  private function clearImportSession($session): void
  {
    $keys = [
      'import_upload_data',
      'import_config',
      'import_detection',
      'import_entity_toggles',
      'import_file',
      'import_entity_type',
      'import_name',
      'import_headers',
      'import_mapping_id',
      'import_field_mappings',
      'import_suggested_mappings',
      'auto_mapping',
    ];
    
    foreach ($keys as $key) {
      $session->remove($key);
    }
  }

  /**
   * Get the appropriate extractor for an entity type
   */
  private function getExtractorForEntity(string $entityType): ?DataExtractor
  {
    # TODO: Use tagged service locator for cleaner DI
    return match($entityType) {
      'stock_location' => $this->locationExtractor,
      'sellable' => $this->catalogExtractor,
      # TODO: 'customer' => $this->container->get(CustomerExtractor::class),
      # TODO: 'order' => $this->container->get(OrderExtractor::class),
      default => null,
    };
  }

  /**
   * Estimate total records based on sample
   */
  private function estimateTotal(int $sampleCount, int $sampleSize, int $totalRows): int
  {
    if ($sampleSize === 0) return 0;
    
    $ratio = $totalRows / $sampleSize;
    return (int) round($sampleCount * $ratio);
  }

  /**
   * Assess how complete a mapping is for an entity type
   */
  private function assessMappingCompleteness(ImportMapping $mapping, string $entityType): array
  {
    $requiredFields = $this->getRequiredFieldsForEntity($entityType);
    $mappedFields = array_values($mapping->getFieldMappings());
    
    $missingRequired = [];
    foreach ($requiredFields as $field => $info) {
      if (!in_array($field, $mappedFields)) {
        $missingRequired[] = [
          'field' => $field,
          'description' => $info['description'] ?? $field,
          'has_default' => $info['has_default'] ?? false,
        ];
      }
    }
    
    return [
      'is_complete' => empty($missingRequired),
      'missing_required' => $missingRequired,
      'mapped_count' => count($mappedFields),
      'completeness_percent' => count($requiredFields) > 0
        ? round((count($requiredFields) - count($missingRequired)) / count($requiredFields) * 100)
        : 100,
    ];
  }

  private function getRequiredFieldsForEntity(string $entityType): array
  {
    return match($entityType) {
      'order' => [
        'order_number' => ['description' => 'Order ID'],
        'order_date' => ['description' => 'Order Date', 'has_default' => true],
      ],
      'order_item' => [
        'order_id' => ['description' => 'Parent Order'],
        'quantity' => ['description' => 'Quantity', 'has_default' => true],
      ],
      'sellable' => [
        'name' => ['description' => 'Product Name'],
        'price' => ['description' => 'Price'],
      ],
      'stock_location' => [
        'name' => ['description' => 'Location Name'],
      ],
      'customer' => [
        'name' => ['description' => 'Customer Name'],
      ],
      'vendor' => [
        'name' => ['description' => 'Vendor Name'],
      ],
      default => [],
    };
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
      default => ucwords(str_replace('_', ' ', $entityType)),
    };
  }

  private function generateCsrfToken(string $tokenId): string
  {
    return $this->csrfTokenManager->getToken($tokenId)->getValue();
  }

  /**
   * Build the batches TableView
   */
  private function buildBatchTable(array $batches, bool $showAll = false): array
  {
    $rows = [];
    foreach ($batches as $batch) {
      $rows[] = TableRow::create([
        'id' => $batch->getId(),
        'name' => $batch->getName() ?: 'Batch #' . $batch->getId(),
        'entity_type' => $batch->getMapping()?->getEntityType() ?? $this->getBatchEntityTypes($batch),
        'status' => $batch->getStatus(),
        'total_rows' => number_format($batch->getTotalRows()),
        'successful' => number_format($batch->getSuccessfulRows()),
        'failed' => number_format($batch->getFailedRows()),
        'started_at' => $batch->getStartedAt(),
      ])
        ->setId($batch->getId())
        ->setStyleClass($this->getBatchRowStyle($batch));
    }
    
    return TableView::create('import-batches')
      ->addField(
        TableField::link('name', 'Import', 'import_batch_show')
          ->sortable()
      )
      ->addField(
        TableField::badge('entity_type', 'Type')
          ->badgeMap([
            'order' => 'primary',
            'item' => 'success',
            'sellable' => 'info',
            'customer' => 'warning',
            'vendor' => 'secondary',
            'multi' => 'dark',
          ])
          ->sortable()
      )
      ->addField(
        TableField::badge('status', 'Status')
          ->badgeMap([
            ImportBatch::STATUS_PENDING => 'secondary',
            ImportBatch::STATUS_PROCESSING => 'info',
            ImportBatch::STATUS_COMPLETED => 'success',
            ImportBatch::STATUS_FAILED => 'danger',
            ImportBatch::STATUS_ROLLED_BACK => 'warning',
          ])
          ->sortable()
      )
      ->addField(TableField::text('total_rows', 'Total')->sortable())
      ->addField(TableField::text('successful', 'Success')->hiddenMobile())
      ->addField(TableField::text('failed', 'Failed')->hiddenMobile())
      ->addField(
        TableField::date('started_at', 'Started', 'M j, g:i A')
          ->sortable()
          ->hiddenMobile()
      )
      ->setRows($rows)
      ->addQuickAction(TableAction::view('import_batch_show'))
      ->setSearchPlaceholder('Search imports...')
      ->setEmptyState('No imports yet. Start by uploading a file!')
      ->build();
  }

  /**
   * Get entity types for multi-entity batch
   */
  private function getBatchEntityTypes(ImportBatch $batch): string
  {
    $entityType = $batch->getMapping()?->getEntityType();
    if (($entityType ?? null) === 'multi') {
      # TODO: 
      # $entities = $metadata['enabled_entities'] ?? [];
      # if (count($entities) > 2) {
      #  return 'multi (' . count($entities) . ')';
      # }
      # return implode(', ', array_map(fn($e) => substr($e, 0, 3), $entities));
      return 'Multiple entities';
    }
    
    return '-';
  }

  /**
   * Build error table
   */
  private function buildErrorTable(array $errors): array
  {
    $rows = [];
    foreach ($errors as $error) {
      $rows[] = TableRow::create([
        'row_number' => $error->getRowNumber(),
        'column' => $error->getColumnName() ?? '-',
        'severity' => $error->getSeverity(),
        'message' => $error->getMessage(),
        'value' => $error->getOriginalValue() ?? '-',
      ])
        ->setStyleClass($this->getErrorRowStyle($error->getSeverity()));
    }
    
    return TableView::create('import-errors')
      ->addField(TableField::text('row_number', 'Row')->sortable())
      ->addField(TableField::text('column', 'Column'))
      ->addField(
        TableField::badge('severity', 'Severity')
          ->badgeMap([
            'critical' => 'danger',
            'error' => 'warning',
            'warning' => 'secondary',
          ])
      )
      ->addField(TableField::text('message', 'Error'))
      ->addField(TableField::text('value', 'Original Value')->hiddenMobile())
      ->setRows($rows)
      ->build();
  }

  /**
   * Build mapping table
   */
  private function buildMappingTable(array $mappings): array
  {
    $rows = [];
    foreach ($mappings as $mapping) {
      $fieldCount = count($mapping->getFieldMappings());
      
      $rows[] = TableRow::create([
        'id' => $mapping->getId(),
        'name' => $mapping->getName(),
        'entity_type' => $mapping->getEntityType(),
        'field_count' => $fieldCount . ' fields',
        'is_template' => $mapping->isSystemTemplate() ? 'System' : 'Custom',
        'updated_at' => $mapping->getUpdatedAt(),
      ])
        ->setId($mapping->getId());
    }
    
    return TableView::create('import-mappings')
      ->addField(
        TableField::link('name', 'Mapping Name', 'import_mapping_show')
          ->sortable()
      )
      ->addField(
        TableField::badge('entity_type', 'Entity Type')
          ->badgeMap([
            'order' => 'primary',
            'item' => 'success',
            'sellable' => 'info',
            'customer' => 'warning',
            'vendor' => 'secondary',
          ])
          ->sortable()
      )
      ->addField(TableField::text('field_count', 'Fields'))
      ->addField(
        TableField::badge('is_template', 'Type')
          ->badgeMap([
            'System' => 'dark',
            'Custom' => 'light',
          ])
      )
      ->addField(
        TableField::date('updated_at', 'Last Updated', 'M j, Y')
          ->sortable()
          ->hiddenMobile()
      )
      ->setRows($rows)
      ->addQuickAction(TableAction::view('import_mapping_show'))
      ->addQuickAction(
        TableAction::create('clone', 'Clone')
          ->setIcon('bi-copy')
          ->setVariant('outline-secondary')
          ->setRoute('import_mapping_clone')
      )
      ->setSearchPlaceholder('Search mappings by name...')
      ->setEmptyState('No import mappings configured yet.')
      ->build();
  }

  /**
   * Build ShowPage for batch detail
   */
  private function buildBatchShowPage(ImportBatch $batch): array
  {
    return ShowPage::create('import-batch-detail')
      ->setHeader(
        ShowPageHeader::create()
          ->setTitle($batch->getName() ?: 'Import Batch #' . $batch->getId())
          ->setSubtitle($this->getBatchSubtitle($batch))
          ->setStatusBadge($batch->getStatus(), $this->getStatusVariant($batch->getStatus()))
          ->addQuickAction(
            PageAction::create('errors', 'View Errors')
              ->setIcon('bi-exclamation-triangle')
              ->setVariant('outline-warning')
              ->setRoute('import_batch_errors', ['id' => $batch->getId()])
          )
          ->addQuickAction(
            PageAction::create('rollback', 'Rollback')
              ->setIcon('bi-arrow-counterclockwise')
              ->setVariant('outline-danger')
              ->setRoute('import_batch_rollback', ['id' => $batch->getId()])
              ->setConfirmMessage('Are you sure? This will delete all data from this import.')
          )
      )
      ->addSection(
        PageSection::createInfoBox('Import Summary')
          ->setColumns(4)
          ->addItem('Total Rows', number_format($batch->getTotalRows()))
          ->addItem('Successful', number_format($batch->getSuccessfulRows()), 'text', 'text-success')
          ->addItem('Failed', number_format($batch->getFailedRows()), 'text', $batch->getFailedRows() > 0 ? 'text-danger' : '')
          ->addItem('Progress', $batch->getProgressPercent() . '%')
      )
      ->addSection(
        PageSection::createInfoBox('Timing')
          ->setColumns(3)
          ->addItem('Started', $batch->getStartedAt()?->format('M j, Y g:i A') ?? '—')
          ->addItem('Completed', $batch->getCompletedAt()?->format('M j, Y g:i A') ?? '—')
          ->addItem('Duration', $this->formatDuration($batch))
      )
      ->setFooter(
        ShowPageFooter::create()
          ->addTerminalAction('back', 'Back to Dashboard', 'import_dashboard', [], 'outline-secondary')
          ->addTerminalAction('new', 'New Import', 'import_upload', [], 'primary')
      )
      ->build();
  }

  private function getBatchSubtitle(ImportBatch $batch): string
  {
    $entityType = $batch->getMapping()?->getEntityType();
    if (($entityType ?? null) === 'multi') {
      # TODO: $entities = $metadata['enabled_entities'] ?? [];
      # return 'Multi-entity import: ' . implode(', ', array_map(
      #  fn($e) => $this->formatEntityLabel($e),
      #  $entities
      # ));
      return 'Multi-entity import';
    }
    return ($batch->getMapping()?->getEntityType() ?? 'Unknown') . ' import';
  }

  /**
   * Build ShowPage for mapping detail
   */
  private function buildMappingShowPage(ImportMapping $mapping, int $usageCount, ?ImportBatch $lastUsed): array
  {
    return ShowPage::create('import-mapping-detail')
      ->setHeader(
        ShowPageHeader::create()
          ->setTitle($mapping->getName())
          ->setSubtitle($mapping->getEntityType() . ' mapping')
          ->setStatusBadge(
            $mapping->isSystemTemplate() ? 'System Template' : 'Custom',
            $mapping->isSystemTemplate() ? 'dark' : 'light'
          )
          ->addQuickAction(
            PageAction::create('use', 'Use This Mapping')
              ->setIcon('bi-play-fill')
              ->setVariant('primary')
              ->setRoute('import_upload', ['mapping' => $mapping->getId()])
          )
          ->addQuickAction(
            PageAction::create('clone', 'Clone')
              ->setIcon('bi-copy')
              ->setVariant('outline-secondary')
              ->setRoute('import_mapping_clone', ['id' => $mapping->getId()])
          )
      )
      ->addSection(
        PageSection::createInfoBox('Usage')
          ->setColumns(3)
          ->addItem('Times Used', $usageCount)
          ->addItem('Last Used', $lastUsed?->getStartedAt()?->format('M j, Y') ?? 'Never')
          ->addItem('Fields Mapped', count($mapping->getFieldMappings()))
      )
      ->build();
  }

  private function getBatchRowStyle(ImportBatch $batch): ?string
  {
    return match ($batch->getStatus()) {
      ImportBatch::STATUS_FAILED => 'table-danger',
      ImportBatch::STATUS_PROCESSING => 'table-info',
      ImportBatch::STATUS_ROLLED_BACK => 'table-warning',
      default => 'table-info',
    };
  }

  private function getErrorRowStyle(string $severity): ?string
  {
    return match ($severity) {
      'critical' => 'table-danger',
      'error' => 'table-warning',
      default => null,
    };
  }

  private function getStatusVariant(string $status): string
  {
    return match ($status) {
      ImportBatch::STATUS_PENDING => 'secondary',
      ImportBatch::STATUS_PROCESSING => 'info',
      ImportBatch::STATUS_COMPLETED => 'success',
      ImportBatch::STATUS_FAILED => 'danger',
      ImportBatch::STATUS_ROLLED_BACK => 'warning',
      default => 'secondary',
    };
  }

  private function formatDuration(ImportBatch $batch): string
  {
    $start = $batch->getStartedAt();
    $end = $batch->getCompletedAt() ?? new \DateTime();
    
    if (!$start) {
      return '—';
    }
    
    $diff = $start->diff($end);
    
    if ($diff->h > 0) {
      return sprintf('%dh %dm', $diff->h, $diff->i);
    }
    if ($diff->i > 0) {
      return sprintf('%dm %ds', $diff->i, $diff->s);
    }
    return sprintf('%ds', $diff->s);
  }
}
