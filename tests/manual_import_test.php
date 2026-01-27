<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Import\DataImportService;
use Doctrine\ORM\EntityManagerInterface;

$kernel = new App\Kernel('test', true);
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

/** @var DataImportService $importService */
$importService = $container->get(DataImportService::class);

/** @var \App\Katzen\Repository\Import\ImportMappingRepository $mappingRepo */
$mappingRepo = $em->getRepository(ImportMapping::class);

$mapping = $mappingRepo->findOneBy(['name' => 'Test Orders Import']);
if (!$mapping) {
    die("Mapping 'Test Orders Import' not found\n");
}

echo "Testing import: tests/fixtures/orders_simple.csv\n";
echo "Mapping: {$mapping->getName()}\n\n";

$progressCallback = function(int $processed, int $total, string $phase) {
    static $lastPhase = '';
    if ($phase !== $lastPhase) {
        echo "\nPhase: $phase (total: $total rows)\n";
        $lastPhase = $phase;
    }
    if ($processed % max(1, intdiv($total, 10)) === 0 || $processed === $total) {
        echo "Progress: $processed / $total (" . round(($processed / $total) * 100) . "%)\n";
    }
};

try {
    $startTime = microtime(true);
    
    $result = $importService->importFromFile(
        'tests/fixtures/orders_simple.csv',
        $mapping,
        [
            'batch_size' => 10,
            'skip_master_data' => false,
            'generate_accounting' => false,
            'dry_run' => false,
            'created_by' => 1,
            'progress_callback' => $progressCallback,
            'max_error_percentage' => 25,
        ]
    );

    $elapsed = microtime(true) - $startTime;

    echo "\n\nIMPORT COMPLETED\n";
    echo str_repeat("=", 50) . "\n\n";

    if ($result->isSuccess()) {
        $data = $result->data;
        
        printf("Batch ID:        %d\n", $data['batch_id']);
        printf("Total Rows:      %d\n", $data['total_rows']);
        printf("Successful:      %d\n", $data['successful_rows']);
        printf("Failed:          %d\n", $data['failed_rows']);
        printf("Success Rate:    %.1f%%\n", ($data['successful_rows'] / $data['total_rows']) * 100);
        printf("Duration:        %.2f seconds\n", $elapsed);
        printf("Throughput:      %.1f rows/sec\n\n", $data['total_rows'] / $elapsed);

        if (!empty($data['entity_counts'])) {
            echo "Entities Created:\n";
            foreach ($data['entity_counts'] as $type => $count) {
                if ($count > 0) {
                    printf("  • %s: %d\n", $type, $count);
                }
            }
        }

        echo "\nSUCCESS - All rows processed successfully!\n";
        exit(0);
    } else {
        echo "IMPORT FAILED\n";
        echo "Errors:\n";
        foreach ($result->errors as $error) {
            echo "  • $error\n";
        }
        exit(1);
    }

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
