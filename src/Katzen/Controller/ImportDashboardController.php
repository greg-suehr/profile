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
use App\Katzen\Repository\Import\ImportBatchRepository;
use App\Katzen\Repository\Import\ImportErrorRepository;
use App\Katzen\Repository\Import\ImportMappingRepository;
use App\Katzen\Repository\Import\ImportMappingLearningRepository;
use App\Katzen\Service\Import\DataImportService;
use App\Katzen\Service\Import\ImportMappingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Import Dashboard Controller
 * 
 * Provides visibility into the import system:
 * - Dashboard with stats and recent batches
 * - Batch detail pages with error analysis
 * - Mapping template management
 * - Four-step import wizard (Upload > Map > Validate > Execute)
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
  ) {}

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

  #[Route('/plan/adjust', name: 'adjust_mapping',  methods: ['POST'])]
  public function adjustMapping(Request $request): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('import_dashboard_batches_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }

    return $this->json(['ok' => false, 'error' => 'Not allowed!'], 400);
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
    # TODO: case 'rollback':
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

  /**
   * Import wizard Step 1: Upload file
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
      $entityType = $data['entity_type'];
      $importName = $data['name'];
      $existingMappingId = $data['use_existing_mapping'];
      
      try {
        $storedFilename = $this->importService->storeUploadedFile($file);
        $headers = $this->importService->extractHeaders($storedFilename);

        # TODO:
        $filepath = $this->getParameter('kernel.project_dir') . '/src/var/uploads/' . $storedFilename;
        $filedata = $this->importService->parseFile($filepath); #, 10); # TODO: tune preview row count for `detectMapping`
        $rows = $filedata->getData()['rows'];
        
        $detectionResult = $this->mappingService->detectMapping($headers, $rows);

        $session = $request->getSession();
        if ($detectionResult->isSuccess()) {

          $detection = $detectionResult->getData();
          $session->set('auto_mapping', $detection);

          return $this->redirectToRoute('import_plan');
        } else {
          # TODO: default weak confidence guesses?
          #       currently loads single entity mapping form with no selections

          $session->set('import_file', $storedFilename);
          $session->set('import_entity_type', $entityType);
          $session->set('import_name', $importName);
          $session->set('import_headers', $headers);
          
          if ($existingMappingId) {
            $session->set('import_mapping_id', $existingMappingId);
            return $this->redirectToRoute('import_validate');
          }

          # TODO: suggest known mappings based on intent context
          # $suggestedMappings = $this->mappingService->s($headers, $entityType);
          # $session->set('import_suggested_mappings', $suggestedMappings);
          
          return $this->redirectToRoute('import_configure_mapping');
        }

        # Oops?
        
      } catch (\Exception $e) {
        $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
      }
    }
    
    return $this->render('katzen/import/upload.html.twig', $this->dashboardContext->with([
      'form' => $form,
    ]));
  }

  /**
   * Import wizard Step 2: Display auto-detect mapping before user confirmation
   */
  #[Route('/plan', name: 'plan')]
  #[DashboardLayout('supply', 'import', 'import-plan')]
  public function plan(Request $request): Response
  {
    $session = $request->getSession();
    $detectionResult = $session->get('auto_mapping');
    $mapping = $detectionResult['mapping'];    
    $column_details = $detectionResult['column_details'];
    $confidence = $detectionResult['confidence'];    
    
    return $this->render('katzen/import/plan.html.twig',  $this->dashboardContext->with([
        'mapping' => $mapping,
        'overall_confidence' => $confidence,
        'column_details' => $column_details,
        'can_refine' => true,
    ]));
  }
  
  /**
   * Import wizard Step 2: Configure mapping
   */
  #[Route('/configure-mapping', name: 'configure_mapping')]
  #[DashboardLayout('supply', 'import', 'import-mapping')]
  public function configureMapping(Request $request): Response
  {
    $session = $request->getSession();
    $headers = $session->get('import_headers');
    $suggestedMappings = $session->get('import_suggested_mappings', []);
    $entityType = $session->get('import_entity_type');
    
    if (!$headers || !$entityType) {
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
   * Import wizard Step 3: Validate data
   */
  #[Route('/validate', name: 'validate')]
  #[DashboardLayout('supply', 'import', 'import-validate')]
  public function validate(Request $request): Response
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
        'validation_results' => $validationResults,
        'mapping' => $mapping,
        'entity_type' => $entityType,
        'can_proceed' => $validationResults['critical_errors'] === 0,
      ]));
      
    } catch (\Exception $e) {
      $this->addFlash('error', 'Validation failed: ' . $e->getMessage());
      return $this->redirectToRoute('import_configure_mapping');
    }
  }

  /**
   * Import wizard Step 4: Execute import
   */
  #[Route('/execute', name: 'execute', methods: ['POST'])]
  public function execute(Request $request): Response
  {
    $session = $request->getSession();
    $filename = $session->get('import_file');
    $entityType = $session->get('import_entity_type');
    $importName = $session->get('import_name');
    $mappingId = $session->get('import_mapping_id');
    $fieldMappings = $session->get('import_field_mappings');
    
    if (!$filename || !$entityType) {
      return $this->json(['error' => 'Missing import data'], 400);
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
      
      $session->remove('import_file');
      $session->remove('import_entity_type');
      $session->remove('import_name');
      $session->remove('import_headers');
      $session->remove('import_mapping_id');
      $session->remove('import_field_mappings');
      $session->remove('import_suggested_mappings');
      
      return $this->json([
        'success' => true,
        'batch_id' => $batch->getId(),
        'redirect_url' => $this->generateUrl('import_batch_show', ['id' => $batch->getId()]),
      ]);
      
    } catch (\Exception $e) {
      return $this->json(['error' => $e->getMessage()], 500);
    }
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
        'entity_type' => $batch->getMapping()?->getEntityType() ?? '-',
        'status' => $batch->getStatus(),
        'total_rows' => number_format($batch->getTotalRows()),
        'successful' => number_format($batch->getSuccessfulRows()),
        'failed' => number_format($batch->getFailedRows()),
        'started_at' => $batch->getStartedAt(),
      ])
          ->setId($batch->getId())
          ->setStyleClass($this->getBatchRowStyle($batch));
    }

    $builder = TableView::create('import-batches')
          ->addField(
            TableField::link('name', 'Import Name', 'import_batch_show')
                  ->sortable()
          )
          ->addField(
            TableField::text('entity_type', 'Type')
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
          ->addField(
            TableField::text('total_rows', 'Total')
                  ->align('right')
                  ->sortable()
          )
          ->addField(
            TableField::text('successful', 'Success')
                  ->align('right')
                  ->hiddenMobile()
          )
          ->addField(
            TableField::text('failed', 'Failed')
                  ->align('right')
                  ->hiddenMobile()
          )
          ->addField(
            TableField::date('started_at', 'Started', 'M j, g:i A')
                  ->sortable()
                  ->hiddenMobile()
          )
          ->setRows($rows)
          ->addQuickAction(
            TableAction::view('import_batch_show')
          )
          ->setSearchPlaceholder('Search imports by name or type...')
          ->setEmptyState('No imports yet. Start your first import to see it here!');
      
    if ($showAll) {
        $builder->setSelectable(true)
              ->addBulkAction(
                TableAction::create('rollback', 'Rollback Selected')
                      ->setIcon('bi-arrow-counterclockwise')
                      ->setVariant('outline-warning')
                      ->setConfirmMessage('Are you sure you want to rollback the selected imports? This will delete all imported data.')
              );
    }
      
    return $builder->build();
  }

  /**
   * Build the error TableView
   */
  private function buildErrorTable(array $errors): array
  {
    $rows = [];
    foreach ($errors as $error) {
      $rows[] = TableRow::create([
        'row_number' => $error->getRowNumber(),
        'error_type' => $error->getErrorType(),
        'severity' => $error->getSeverity(),
        'field' => $error->getFieldName() ?? '—',
        'message' => $error->getErrorMessage(),
        'suggested_fix' => $error->getSuggestedFix() ?? '—',
      ])
          ->setId($error->getId())
          ->setStyleClass($this->getErrorRowStyle($error->getSeverity()));
    }
    
    return TableView::create('import-errors')
          ->addField(TableField::text('row_number', 'Row')->sortable())
          ->addField(
            TableField::badge('error_type', 'Type')
                  ->badgeMap([
                    'validation' => 'warning',
                    'transformation' => 'info',
                    'entity_creation' => 'danger',
                    'duplicate' => 'secondary',
                    'reference' => 'dark',
                  ])
          )
          ->addField(
            TableField::badge('severity', 'Severity')
                  ->badgeMap([
                    'warning' => 'warning',
                    'error' => 'danger',
                    'critical' => 'dark',
                  ])
          )
          ->addField(TableField::text('field', 'Field'))
          ->addField(TableField::text('message', 'Message'))
          ->addField(TableField::text('suggested_fix', 'Suggested Fix')->hiddenMobile())
          ->setRows($rows)
          ->setShowToolbar(false)
          ->setEmptyState('No errors found!')
          ->build();
  }

  /**
   * Build the mappings TableView
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
                  ->setSubtitle($batch->getMapping()?->getEntityType() . ' import')
                  ->setStatusBadge($batch->getStatus(), $this->getStatusVariant($batch->getStatus()))
                  ->addQuickAction(
                    PageAction::create('errors', 'View Errors')
                          ->setIcon('bi-exclamation-triangle')
                          ->setVariant('outline-warning')
                          ->setRoute('import_batch_errors', ['id' => $batch->getId()])
                          # TODO: conditional PageActions: ->setDisabled($batch->getFailedRows() === 0)
                  )
                  ->addQuickAction(
                    PageAction::create('rollback', 'Rollback')
                          ->setIcon('bi-arrow-counterclockwise')
                          ->setVariant('outline-danger')
                          ->setRoute('import_batch_rollback', ['id' => $batch->getId()])
                          ->setConfirmMessage('Are you sure? This will delete all data from this import.')
                          # TODO: conditional PageActions: ->setDisabled(!$batch->canRollback())
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
                  ->addTerminalAction(
                    'back',
                    'Back to Dashboard',
                    'import_dashboard',
                    [],
                    'outline-secondary',
                    # TODO: ->setIcon('bi-arrow-left')
                  )
                  ->addTerminalAction(
                    'new',
                    'New Import',
                    'import_upload',
                    [],
                    'primary'
                    # TODO: ->setIcon('bi-plus-lg')
                  )
          )
          ->build();
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

  /**
   * Get row styling based on batch status
   */
  private function getBatchRowStyle(ImportBatch $batch): ?string
  {
    return match ($batch->getStatus()) {
      ImportBatch::STATUS_FAILED => 'table-danger',
      ImportBatch::STATUS_PROCESSING => 'table-info',
      ImportBatch::STATUS_ROLLED_BACK => 'table-warning',
      default => 'table-info',
    };
  }

  /**
   * Get row styling for error severity
   */
  private function getErrorRowStyle(string $severity): ?string
  {
    return match ($severity) {
      'critical' => 'table-danger',
      'error' => 'table-warning',
      default => null,
    };
  }

  /**
   * Get Bootstrap variant for status badge
   */
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

  /**
   * Format batch duration
   */
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
