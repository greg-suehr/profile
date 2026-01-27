<?php

namespace App\Katzen\Command;

use App\Katzen\Repository\Import\ImportMappingRepository;
use App\Katzen\Service\Import\DataImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:test-sync',
    description: 'Test synchronous import with a CSV file'
)]
class TestSyncImportCommand extends Command
{
    public function __construct(
        private DataImportService $importService,
        private ImportMappingRepository $mappingRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'CSV file path')
            ->addArgument('mapping', InputArgument::REQUIRED, 'Mapping name (e.g., "Test Orders Import")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filepath = $input->getArgument('file');
        $mappingName = $input->getArgument('mapping');

        if (!file_exists($filepath)) {
            $io->error("File not found: {$filepath}");
            return Command::FAILURE;
        }

        $mapping = $this->mappingRepo->findOneBy(['name' => $mappingName]);
        if (!$mapping) {
            $io->error("Mapping not found: {$mappingName}");
            return Command::FAILURE;
        }

        $io->section("Starting Synchronous Import Test");
        $io->text([
            "File: <fg=cyan>{$filepath}</>",
            "Mapping: <fg=cyan>{$mappingName}</> (Entity Type: {$mapping->getEntityType()})",
        ]);

        $progressBar = null;
        
        $progressCallback = function(int $processed, int $total, string $phase) use ($io, &$progressBar) {
            static $lastPhase = null;
            
            if ($phase !== $lastPhase) {
                if ($progressBar) {
                    $progressBar->finish();
                    $io->writeln('');
                }
                $lastPhase = $phase;
                $io->text("Phase: <fg=yellow>{$phase}</> ({$total} rows)");
                $progressBar = new ProgressBar($io, $total);
                $progressBar->start();
            }
            
            if ($progressBar && $total > 0) {
                $progressBar->setProgress($processed);
            }
        };

        try {
            $startTime = microtime(true);
            
            $result = $this->importService->importFromFile(
                $filepath,
                $mapping,
                [
                    'batch_size' => 10,
                    'skip_master_data' => false,
                    'generate_accounting' => false,
                    'dry_run' => false,
                    'created_by' => null, # TODO: find_or_create_system_user
                    'progress_callback' => $progressCallback,
                    'max_error_percentage' => 25,
                ]
            );

            $elapsed = microtime(true) - $startTime;
            
            if ($progressBar) {
                $progressBar->finish();
                $io->writeln('');
            }

            $io->section("Import Completed");            
            
            if ($result->isSuccess()) {
                $data = $result->data;
                
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Batch ID', $data['batch_id']],
                        ['Total Rows', $data['total_rows']],
                        ['Successful Rows', $data['successful_rows']],
                        ['Failed Rows', $data['failed_rows']],
                        ['Duration', sprintf('%.2f seconds', $elapsed)],
                        ['Rows/Second', sprintf('%.1f', $data['total_rows'] / $elapsed)],
                    ]
                );

                if (!empty($data['entity_counts'])) {
                    $io->section("Entities Created");
                    $rows = [];
                    foreach ($data['entity_counts'] as $type => $count) {
                        if ($count > 0) {
                            $rows[] = [$type, $count];
                        }
                    }
                    if (!empty($rows)) {
                        $io->table(['Entity Type', 'Count'], $rows);
                    }
                }

                if (!empty($data['error_summary']) && !empty($data['error_summary']['errors'])) {
                    $io->section("Errors Found");
                    foreach (array_slice($data['error_summary']['errors'], 0, 10) as $error) {
                        $io->writeln("• {$error}");
                    }
                    if (count($data['error_summary']['errors']) > 10) {
                        $io->writeln("• ... and " . (count($data['error_summary']['errors']) - 10) . " more");
                    }
                }

                $io->success("Import successful!");
                return Command::SUCCESS;
            } else {
                $io->error("Import failed!");
                foreach ($result->errors as $error) {
                    $io->writeln("{$error}");
                }
                return Command::FAILURE;
            }

        } catch (\Throwable $e) {
            $io->error("Import threw exception: " . $e->getMessage());
            if ($input->getOption('verbose')) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
