<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Master Data Extractor
 * 
 * Extracts and creates master data entities (Items, Sellables, Locations, 
 * Customers, Vendors) from import rows before processing transactional data.
 * 
 * Uses find-or-create pattern to prevent duplicates while ensuring
 * all referenced entities exist.
 */
final class MasterDataExtractor
{
  public function __construct(
    private EntityManagerInterface $em,
    private LoggerInterface $logger,
    // TODO: add `createFromImport` methods and inject ...
    // private ItemService $itemService,
    // private SellableService $sellableService,
    // private CustomerService $customerService,
    // private VendorService $vendorService,
    // private StockLocationService $locationService,
  ) {}

  /**
   * Extract unique master data records from rows
   * 
   * Scans all rows to identify unique entities that need to exist
   * before transactional data can be imported.
   * 
   * @return array{
   *   items: array<string, array>,
   *   sellables: array<string, array>,
   *   customers: array<string, array>,
   *   vendors: array<string, array>,
   *   locations: array<string, array>
   * }
   */
  public function extract(array $rows, ImportMapping $mapping): array
  {
    $entityType = $mapping->getEntityType();
    $fieldMappings = $mapping->getFieldMappings();
    
    $masterData = [
      'items' => [],
      'sellables' => [],
      'customers' => [],
      'vendors' => [],
      'locations' => [],
    ];
    
    foreach ($rows as $row) {
      $this->extractFromRow($row, $entityType, $fieldMappings, $masterData);
    }
    
    $this->logger->info('Master data extracted', [
      'items' => count($masterData['items']),
      'sellables' => count($masterData['sellables']),
      'customers' => count($masterData['customers']),
      'vendors' => count($masterData['vendors']),
      'locations' => count($masterData['locations']),
    ]);
    
    return $masterData;
  }
  
  /**
   * Extract master data references from a single row
   */
  private function extractFromRow(
    array $row,
    string $entityType,
    array $fieldMappings,
    array &$masterData
  ): void {
    switch ($entityType) {
    case 'order':
    case 'order_line':
      $this->extractOrderMasterData($row, $fieldMappings, $masterData);
      break;
      
    case 'purchase':
    case 'purchase_line':
      $this->extractPurchaseMasterData($row, $fieldMappings, $masterData);
      break;
      
    case 'item':
      // Item imports might reference categories, locations
      $this->extractItemMasterData($row, $fieldMappings, $masterData);
      break;
      
    case 'sellable':
      // Sellable imports might reference items, categories
      $this->extractSellableMasterData($row, $fieldMappings, $masterData);
      break;
    }
  }

  /**
   * Extract master data from order rows
   */
  private function extractOrderMasterData(
    array $row,
    array $fieldMappings,
    array &$masterData
  ): void {
    $sellableColumn = $this->findColumnForField('sellable', $fieldMappings);
    if ($sellableColumn && !empty($row[$sellableColumn])) {
      $sellableKey = $this->normalizeKey($row[$sellableColumn]);
      if (!isset($masterData['sellables'][$sellableKey])) {
        $masterData['sellables'][$sellableKey] = [
          'name' => trim($row[$sellableColumn]),
          'sku' => $row[$this->findColumnForField('sku', $fieldMappings) ?? ''] ?? null,
          'price' => $this->extractNumeric($row, 'unit_price', $fieldMappings),
        ];
      }
    }
        
    $customerColumn = $this->findColumnForField('customer', $fieldMappings);
    if ($customerColumn && !empty($row[$customerColumn])) {
      $customerKey = $this->normalizeKey($row[$customerColumn]);
      if (!isset($masterData['customers'][$customerKey])) {
        $masterData['customers'][$customerKey] = [
          'name' => trim($row[$customerColumn]),
          'email' => $row[$this->findColumnForField('customer_email', $fieldMappings) ?? ''] ?? null,
          'phone' => $row[$this->findColumnForField('customer_phone', $fieldMappings) ?? ''] ?? null,
        ];
      }
    }
    
    $locationColumn = $this->findColumnForField('location', $fieldMappings);
    if ($locationColumn && !empty($row[$locationColumn])) {
      $locationKey = $this->normalizeKey($row[$locationColumn]);
      if (!isset($masterData['locations'][$locationKey])) {
        $masterData['locations'][$locationKey] = [
          'name' => trim($row[$locationColumn]),
        ];
      }
    }
  }

  /**
   * Extract master data from purchase rows
   */
  private function extractPurchaseMasterData(
    array $row,
    array $fieldMappings,
    array &$masterData
  ): void {
    $itemColumn = $this->findColumnForField('item', $fieldMappings);
    if ($itemColumn && !empty($row[$itemColumn])) {
      $itemKey = $this->normalizeKey($row[$itemColumn]);
      if (!isset($masterData['items'][$itemKey])) {
        $masterData['items'][$itemKey] = [
          'name' => trim($row[$itemColumn]),
          'sku' => $row[$this->findColumnForField('sku', $fieldMappings) ?? ''] ?? null,
          'category' => $row[$this->findColumnForField('category', $fieldMappings) ?? ''] ?? null,
          'unit' => $row[$this->findColumnForField('unit', $fieldMappings) ?? ''] ?? null,
        ];
      }
    }
        
    $vendorColumn = $this->findColumnForField('vendor', $fieldMappings);
    if ($vendorColumn && !empty($row[$vendorColumn])) {
      $vendorKey = $this->normalizeKey($row[$vendorColumn]);
      if (!isset($masterData['vendors'][$vendorKey])) {
        $masterData['vendors'][$vendorKey] = [
          'name' => trim($row[$vendorColumn]),
          'contact' => $row[$this->findColumnForField('vendor_contact', $fieldMappings) ?? ''] ?? null,
          'email' => $row[$this->findColumnForField('vendor_email', $fieldMappings) ?? ''] ?? null,
        ];
      }
    }
    
    $locationColumn = $this->findColumnForField('location', $fieldMappings);
    if ($locationColumn && !empty($row[$locationColumn])) {
      $locationKey = $this->normalizeKey($row[$locationColumn]);
      if (!isset($masterData['locations'][$locationKey])) {
        $masterData['locations'][$locationKey] = [
          'name' => trim($row[$locationColumn]),
        ];
      }
    }
  }

  /**
   * Extract master data from item rows
   */
  private function extractItemMasterData(
    array $row,
    array $fieldMappings,
    array &$masterData
  ): void {
    $locationColumn = $this->findColumnForField('storage_location', $fieldMappings)
            ?? $this->findColumnForField('location', $fieldMappings);
        
    if ($locationColumn && !empty($row[$locationColumn])) {
      $locationKey = $this->normalizeKey($row[$locationColumn]);
      if (!isset($masterData['locations'][$locationKey])) {
        $masterData['locations'][$locationKey] = [
          'name' => trim($row[$locationColumn]),
        ];
      }
    }
  }
  
  /**
   * Extract master data from sellable rows
   */
  private function extractSellableMasterData(
    array $row,
    array $fieldMappings,
    array &$masterData
  ): void {
    $itemColumn = $this->findColumnForField('base_item', $fieldMappings)
            ?? $this->findColumnForField('item', $fieldMappings);
    
    if ($itemColumn && !empty($row[$itemColumn])) {
      $itemKey = $this->normalizeKey($row[$itemColumn]);
      if (!isset($masterData['items'][$itemKey])) {
        $masterData['items'][$itemKey] = [
          'name' => trim($row[$itemColumn]),
        ];
      }
    }
  }

  /**
   * Create entities from extracted master data
   */
  public function createEntities(
    array $masterData,
    string $primaryEntityType,
    ImportBatch $batch
  ): ServiceResponse {
    $entityCounts = [
      'items' => 0,
      'sellables' => 0,
      'customers' => 0,
      'vendors' => 0,
      'locations' => 0,
    ];
    
    $errors = [];
    
    try {
      foreach ($masterData['locations'] as $key => $data) {
        $result = $this->findOrCreateLocation($data, $batch);
        if ($result->isSuccess()) {
          $entityCounts['locations']++;
        } elseif ($result->data['was_created'] ?? false) {
          $entityCounts['locations']++;
        }
      }
      
      foreach ($masterData['items'] as $key => $data) {
        $result = $this->findOrCreateItem($data, $batch);
        if ($result->isSuccess() && ($result->data['was_created'] ?? false)) {
          $entityCounts['items']++;
        }
      }
      
      foreach ($masterData['sellables'] as $key => $data) {
        $result = $this->findOrCreateSellable($data, $batch);
        if ($result->isSuccess() && ($result->data['was_created'] ?? false)) {
          $entityCounts['sellables']++;
        }
      }
      
      foreach ($masterData['customers'] as $key => $data) {
        $result = $this->findOrCreateCustomer($data, $batch);
        if ($result->isSuccess() && ($result->data['was_created'] ?? false)) {
          $entityCounts['customers']++;
        }
      }
      
      foreach ($masterData['vendors'] as $key => $data) {
        $result = $this->findOrCreateVendor($data, $batch);
        if ($result->isSuccess() && ($result->data['was_created'] ?? false)) {
          $entityCounts['vendors']++;
        }
      }
            
      $this->em->flush();
      
      return ServiceResponse::success(
        data: ['entity_counts' => $entityCounts],
        message: 'Master data created successfully'
      );
      
    } catch (\Throwable $e) {
      $this->logger->error('Master data creation failed', [
        'error' => $e->getMessage(),
      ]);
      
      return ServiceResponse::failure(
        errors: ['Master data creation failed: ' . $e->getMessage()],
        data: ['entity_counts' => $entityCounts]
      );
    }
  }

  /**
   * Find or create a stock location
   */
  private function findOrCreateLocation(array $data, ImportBatch $batch): ServiceResponse
  {
    $name = trim($data['name']);
        
    $locationRepo = $this->em->getRepository(\App\Katzen\Entity\StockLocation::class);
    $existing = $locationRepo->findOneBy(['name' => $name]);
    
    if ($existing) {
      return ServiceResponse::success(
        data: ['entity' => $existing, 'was_created' => false]
      );
    }
    
    $location = new \App\Katzen\Entity\StockLocation();
    $location->setName($name);
    // TODO: Track which batch created this for rollback support
    // $location->setImportBatchId($batch->getId());
        
    $this->em->persist($location);
    
    return ServiceResponse::success(
      data: ['entity' => $location, 'was_created' => true]
    );
  }

  /**
   * Find or create an item
   */
  private function findOrCreateItem(array $data, ImportBatch $batch): ServiceResponse
  {
    $name = trim($data['name']);
    $sku = $data['sku'] ?? null;
    
    $itemRepo = $this->em->getRepository(\App\Katzen\Entity\Item::class);
    
    $existing = null;
    if ($sku) {
      $existing = $itemRepo->findOneBy(['sku' => $sku]);
    }
    if (!$existing) {
      $existing = $itemRepo->findOneBy(['name' => $name]);
    }
    
    if ($existing) {
      return ServiceResponse::success(
        data: ['entity' => $existing, 'was_created' => false]
      );
    }
    
    $item = new \App\Katzen\Entity\Item();
    $item->setName($name);
    if ($sku) {
      $item->setSku($sku);
    }
    if ($data['category'] ?? null) {
      # TODO: findOrCreate the category too
      # $item->setCategory($category);
    }
    # TODO: Track batch for rollback
    # $item->setImportBatchId($batch->getId());
    
    $this->em->persist($item);
    
    return ServiceResponse::success(
      data: ['entity' => $item, 'was_created' => true]
    );
  }
  
  /**
   * Find or create a sellable
   */
  private function findOrCreateSellable(array $data, ImportBatch $batch): ServiceResponse
  {
    $name = trim($data['name']);
    $sku = $data['sku'] ?? null;
    $price = $data['price'] ?? null;
    
    $sellableRepo = $this->em->getRepository(\App\Katzen\Entity\Sellable::class);
        
    $existing = null;
    if ($sku) {
      $existing = $sellableRepo->findOneBy(['sku' => $sku]);
    }
    if (!$existing) {
      $existing = $sellableRepo->findOneBy(['name' => $name]);
    }
    
    if ($existing) {
      return ServiceResponse::success(
        data: ['entity' => $existing, 'was_created' => false]
      );
    }
    
    $sellable = new \App\Katzen\Entity\Sellable();
    $sellable->setName($name);
    if ($sku) {
      $sellable->setSku($sku);
    }
    if ($price !== null) {
      $sellable->setBasePrice((string) $price);
    }
    # TODO: Track sellable.import_batch_id
    # $sellable->setImportBatchId($batch->getId());
        
    $this->em->persist($sellable);
    
    return ServiceResponse::success(
      data: ['entity' => $sellable, 'was_created' => true]
    );
  }

  /**
   * Find or create a customer
   */
  private function findOrCreateCustomer(array $data, ImportBatch $batch): ServiceResponse
  {
    $name = trim($data['name']);
    $email = $data['email'] ?? null;
    
    $customerRepo = $this->em->getRepository(\App\Katzen\Entity\Customer::class);
    
    $existing = null;
    if ($email) {
      $existing = $customerRepo->findOneBy(['email' => $email]);
    }
    if (!$existing) {
      $existing = $customerRepo->findOneBy(['name' => $name]);
    }
    
    if ($existing) {
      return ServiceResponse::success(
        data: ['entity' => $existing, 'was_created' => false]
      );
    }
        
    $customer = new \App\Katzen\Entity\Customer();
    $customer->setName($name);
    if ($email) {
      $customer->setEmail($email);
    }
    if ($data['phone'] ?? null) {
      $customer->setPhone($data['phone']);
    }
    # TODO: track custoemr.import_batch_id
    # $customer->setImportBatchId($batch->getId());
        
    $this->em->persist($customer);
        
    return ServiceResponse::success(
      data: ['entity' => $customer, 'was_created' => true]
    );
  }

  /**
   * Find or create a vendor
   */
  private function findOrCreateVendor(array $data, ImportBatch $batch): ServiceResponse
  {
    $name = trim($data['name']);
        
    $vendorRepo = $this->em->getRepository(\App\Katzen\Entity\Vendor::class);
    $existing = $vendorRepo->findOneBy(['name' => $name]);
    
    if ($existing) {
      return ServiceResponse::success(
        data: ['entity' => $existing, 'was_created' => false]
      );
    }
        
    $vendor = new \App\Katzen\Entity\Vendor();
    $vendor->setName($name);
    if ($data['email'] ?? null) {
      $vendor->setEmail($data['email']);
    }
    if ($data['contact'] ?? null) {
      $vendor->setContactName($data['contact']);
    }
    # TODO: Track vendor.import_batch_id
    # $vendor->setImportBatchId($batch->getId());
        
    $this->em->persist($vendor);
        
    return ServiceResponse::success(
      data: ['entity' => $vendor, 'was_created' => true]
    );
  }

  /**
   * Helper: Find column that maps to a field
   */
  private function findColumnForField(string $field, array $fieldMappings): ?string
  {
    foreach ($fieldMappings as $column => $config) {
      $targetField = is_array($config) 
        ? ($config['target_field'] ?? $config['field'] ?? null) 
        : $config;
      if ($targetField === $field) {
        return $column;
      }
    }
    return null;
  }

  /**
   * Helper: Normalize key for deduplication
   */
  private function normalizeKey(string $value): string
  {
    return strtolower(trim($value));
  }

  /**
   * Helper: Extract numeric value from row
   */
  private function extractNumeric(array $row, string $field, array $fieldMappings): ?float
  {
    $column = $this->findColumnForField($field, $fieldMappings);
    if (!$column || !isset($row[$column])) {
      return null;
    }
    
    $value = str_replace(['$', ',', '€', '£', ' '], '', $row[$column]);
    return is_numeric($value) ? (float) $value : null;
  }
}
