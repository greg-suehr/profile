<?php

namespace App\Katzen\Service\Import\Extractor;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Repository\StockLocationRepository;
use App\Katzen\Service\Import\ExtractionResult;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Location Extractor
 * 
 * Extracts StockLocation entities from import data. Handles common patterns
 * in food service location data:
 * 
 * - Store/location names from POS exports (e.g., "Lower Manhattan", "Store #42")
 * - Warehouse/storage locations from inventory systems
 * - Kitchen stations from prep/production data
 * - External IDs for system integration
 * 
 * Location data is typically simple (name + optional external ID), so this
 * extractor focuses on accurate deduplication and external ID preservation
 * for multi-system synchronization.
 * 
 * @example Sample data:
 * ```csv
 * store_location,store_id,region
 * "Lower Manhattan",LM001,NYC
 * "Upper West Side",UWS002,NYC
 * "Brooklyn Heights",BH003,NYC
 * ```
 */
final class LocationExtractor extends AbstractDataExtractor
{
    protected const DEFAULT_PRIORITY = 90; // Run early - locations are dependencies
    
    /**
     * Location type classification based on naming patterns.
     */
    private const LOCATION_TYPES = [
        'store' => ['store', 'shop', 'retail', 'outlet', 'branch'],
        'warehouse' => ['warehouse', 'wh', 'distribution', 'dc', 'fulfillment'],
        'kitchen' => ['kitchen', 'prep', 'production', 'commissary', 'central'],
        'station' => ['station', 'line', 'counter', 'window'],
        'storage' => ['storage', 'cooler', 'freezer', 'pantry', 'dry storage'],
        'event' => ['market', 'fair', 'popup', 'pop-up', 'event', 'booth'],
    ];
    
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        private StockLocationRepository $locationRepo,
    ) {
        parent::__construct($em, $logger);
    }
    
    public function getLabel(): string
    {
        return 'Stock Locations';
    }
    
    public function getEntityTypes(): array
    {
        return ['stock_location'];
    }
    
    protected function getDetectionHeaders(): array
    {
        return [
            ['location', 'store_location', 'store', 'warehouse', 'site'],
            ['location_name', 'store_name', 'warehouse_name', 'site_name'],
        ];
    }
    
    protected function getStrongIndicators(): array
    {
        return [
            'location_id', 'store_id', 'warehouse_id', 'site_id',
            'location_code', 'store_code', 'store_number',
            'address', 'region', 'district', 'zone',
        ];
    }
    
    protected function extractRecords(
        array $rows,
        array $headers,
        ImportMapping $mapping
    ): ExtractionResult {
        $fieldMappings = $mapping->getFieldMappings();
        
        // Find location-related columns
        $nameColumn = $this->findColumn(
            ['location', 'store_location', 'location_name', 'store_name', 
             'store', 'warehouse', 'site', 'site_name'],
            $headers
        );
        
        $idColumn = $this->findColumn(
            ['location_id', 'store_id', 'warehouse_id', 'site_id', 
             'location_code', 'store_code', 'store_number', 'external_id'],
            $headers
        );
        
        $addressColumn = $this->findColumn(
            ['address', 'street_address', 'location_address'],
            $headers
        );
        
        $regionColumn = $this->findColumn(
            ['region', 'district', 'zone', 'area', 'territory'],
            $headers
        );
        
        $typeColumn = $this->findColumn(
            ['location_type', 'store_type', 'type'],
            $headers
        );
        
        $locations = [];
        $byRegion = [];
        $byType = [];
        $warnings = [];
        
        foreach ($rows as $rowIndex => $row) {
            $name = $this->getValue($row, $nameColumn);
            
            // Skip rows without location data
            if (empty($name)) {
                continue;
            }
            
            $externalId = $this->getValue($row, $idColumn);
            $address = $this->getValue($row, $addressColumn);
            $region = $this->getValue($row, $regionColumn);
            $explicitType = $this->getValue($row, $typeColumn);
            
            // Build unique key - prefer external ID, fall back to name
            $key = $externalId 
                ? 'ext:' . $this->normalizeKey($externalId)
                : 'name:' . $this->normalizeKey($name);
            
            if (!isset($locations[$key])) {
                // Infer location type from name if not explicit
                $locationType = $explicitType ?: $this->inferLocationType($name);
                
                $locations[$key] = [
                    'name' => $name,
                    'external_id' => $externalId,
                    'address' => $address,
                    'region' => $region,
                    'type' => $locationType,
                    'row_count' => 0,
                    'first_seen_row' => $rowIndex,
                ];
                
                // Track by region
                if ($region) {
                    $byRegion[$region] = ($byRegion[$region] ?? 0) + 1;
                }
                
                // Track by type
                if ($locationType) {
                    $byType[$locationType] = ($byType[$locationType] ?? 0) + 1;
                }
            }
            
            $locations[$key]['row_count']++;
            
            // Check for data conflicts (same key but different values)
            if ($locations[$key]['name'] !== $name) {
                $warnings[] = sprintf(
                    'Location key "%s" has conflicting names: "%s" vs "%s" (row %d)',
                    $key, $locations[$key]['name'], $name, $rowIndex + 1
                );
            }
        }
        
        // Sort locations by row count (most common first)
        uasort($locations, fn($a, $b) => $b['row_count'] <=> $a['row_count']);
        
        $diagnostics = [
            'total_rows' => count($rows),
            'unique_locations' => count($locations),
            'by_region' => $byRegion,
            'by_type' => $byType,
            'has_external_ids' => $idColumn !== null,
            'columns_found' => array_filter([
                'name' => $nameColumn,
                'id' => $idColumn,
                'address' => $addressColumn,
                'region' => $regionColumn,
                'type' => $typeColumn,
            ]),
        ];
        
        return new ExtractionResult(
            records: ['locations' => $locations],
            diagnostics: $diagnostics,
            warnings: $warnings,
            metadata: [
                'column_mappings' => [
                    'name' => $nameColumn,
                    'external_id' => $idColumn,
                    'address' => $addressColumn,
                    'region' => $regionColumn,
                    'type' => $typeColumn,
                ],
            ],
        );
    }
    
    public function createEntities(array $extractedRecords, ImportBatch $batch): ServiceResponse
    {
        $locations = $extractedRecords['locations'] ?? [];
        $entityCounts = [
            'locations_created' => 0,
            'locations_found' => 0,
            'locations_updated' => 0,
        ];
        $errors = [];
        $createdEntities = [];
        
        try {
            foreach ($locations as $key => $data) {
                $result = $this->findOrCreateLocation($data, $batch);
                
                if ($result->isSuccess()) {
                    $entity = $result->data['entity'];
                    $createdEntities[$key] = $entity;
                    
                    if ($result->data['was_created'] ?? false) {
                        $entityCounts['locations_created']++;
                    } elseif ($result->data['was_updated'] ?? false) {
                        $entityCounts['locations_updated']++;
                    } else {
                        $entityCounts['locations_found']++;
                    }
                } else {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
            
            $this->em->flush();
            
            if (!empty($errors)) {
                return $this->failureResponse($errors, $entityCounts);
            }
            
            return ServiceResponse::success(
                data: [
                    'entity_counts' => $entityCounts,
                    'entities' => $createdEntities,
                ],
                message: sprintf(
                    'Processed %d locations (%d created, %d existing, %d updated)',
                    count($locations),
                    $entityCounts['locations_created'],
                    $entityCounts['locations_found'],
                    $entityCounts['locations_updated']
                )
            );
            
        } catch (\Throwable $e) {
            $this->logger->error('Location entity creation failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->failureResponse(
                ['Location creation failed: ' . $e->getMessage()],
                $entityCounts
            );
        }
    }
    
    // ========================================================================
    // Private Helper Methods
    // ========================================================================
    
    /**
     * Infer location type from name patterns.
     */
    private function inferLocationType(string $name): ?string
    {
        $lowerName = strtolower($name);
        
        foreach (self::LOCATION_TYPES as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lowerName, $pattern)) {
                    return $type;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find or create a StockLocation entity.
     */
    private function findOrCreateLocation(array $data, ImportBatch $batch): ServiceResponse
    {
        $name = trim($data['name'] ?? '');
        $externalId = $data['external_id'] ?? null;
        
        if (empty($name)) {
            return ServiceResponse::failure('Location must have a name');
        }
        
        // Try to find existing location
        $existing = null;
        $wasUpdated = false;
        
        // First try by external ID (most reliable)
        if ($externalId) {
            $existing = $this->locationRepo->findOneBy(['externalId' => $externalId]);
        }
        
        // Fall back to name lookup
        if (!$existing) {
            $existing = $this->locationRepo->findOneBy(['name' => $name]);
        }
        
        if ($existing) {
            // Optionally update external ID if we found by name but have new external ID
            if ($externalId && !$existing->getExternalId()) {
                $existing->setExternalId($externalId);
                $wasUpdated = true;
            }
            
            return ServiceResponse::success(
                data: [
                    'entity' => $existing, 
                    'was_created' => false,
                    'was_updated' => $wasUpdated,
                ]
            );
        }
        
        // Create new location
        $location = new StockLocation();
        $location->setName($name);
        
        if ($externalId) {
            $location->setExternalId($externalId);
        }
        
        // Set additional fields if the entity supports them
        // These might not exist on your StockLocation - adjust as needed
        if (method_exists($location, 'setAddress') && ($data['address'] ?? null)) {
            $location->setAddress($data['address']);
        }
        
        if (method_exists($location, 'setLocationType') && ($data['type'] ?? null)) {
            $location->setLocationType($data['type']);
        }
        
        if (method_exists($location, 'setRegion') && ($data['region'] ?? null)) {
            $location->setRegion($data['region']);
        }
        
        $this->em->persist($location);
        
        return ServiceResponse::success(
            data: ['entity' => $location, 'was_created' => true]
        );
    }
    
    // ========================================================================
    // Public Utility Methods
    // ========================================================================
    
    /**
     * Get available location types for UI display.
     */
    public static function getLocationTypes(): array
    {
        return [
            'store' => ['label' => 'Store/Retail', 'icon' => 'bi-shop'],
            'warehouse' => ['label' => 'Warehouse', 'icon' => 'bi-building'],
            'kitchen' => ['label' => 'Kitchen/Production', 'icon' => 'bi-cup-hot'],
            'station' => ['label' => 'Station', 'icon' => 'bi-grid-3x3'],
            'storage' => ['label' => 'Storage', 'icon' => 'bi-box-seam'],
            'event' => ['label' => 'Event/Market', 'icon' => 'bi-calendar-event'],
        ];
    }
    
    /**
     * Validate a location name.
     */
    public function validateLocationName(string $name): array
    {
        $errors = [];
        
        if (strlen($name) < 2) {
            $errors[] = 'Location name must be at least 2 characters';
        }
        
        if (strlen($name) > 255) {
            $errors[] = 'Location name must be less than 255 characters';
        }
        
        if (preg_match('/^[0-9]+$/', $name)) {
            $errors[] = 'Location name cannot be only numbers';
        }
        
        return $errors;
    }
}
