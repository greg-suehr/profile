<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\{
    Account,
    Item,
    ItemVariant,
    ItemUPC,
    StockTarget,
    Unit,
    Vendor,
    VendorInvoice,
    VendorInvoiceItem
};
use App\Katzen\Repository\{
    AccountRepository,
    ItemRepository,
    ItemUPCRepository,
    ItemVariantRepository,
    StockTargetRepository,
    UnitRepository,
    VendorInvoiceRepository,
    VendorRepository
};
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Receipt Import Service
 * 
 * Processes OCR-scanned vendor receipts and creates draft vendor invoices.
 * Handles item matching via UPC, auto-creation of unknown items, and 
 * comprehensive data validation with detailed error reporting.
 */
final class ReceiptImportService
{
  # TODO: move to user configuration
  private const DEFAULT_EXPENSE_ACCOUNT_CODE = '5000';
  private const DEFAULT_UOM = 'each';
  private const DEFAULT_ITEM_CATEGORY = 'Miscellaneous';
  
  public function __construct(
    private EntityManagerInterface $em,
    private VendorInvoiceRepository $invoiceRepo,
    private VendorRepository $vendorRepo,
    private ItemRepository $itemRepo,
    private ItemVariantRepository $itemVariantRepo,
    private ItemUPCRepository $itemUpcRepo,
    private StockTargetRepository $stockTargetRepo,
    private AccountRepository $accountRepo,
    private UnitRepository $unitRepo,
    private LoggerInterface $logger,
  ) {}
  
  /**
   * Process OCR result and create draft vendor invoice
   *
   * @param array $ocrData The OCR extraction result
   * @param int $userId The user creating this import
   * @return ServiceResponse Success with invoice_id or failure with errors
   */
  public function processOCRResult(array $ocrData, int $userId): ServiceResponse
  {
    $this->logger->info('Starting OCR import process', [
      'user_id' => $userId,
      'avg_confidence' => $ocrData['confidence_report']['avg'] ?? 0,
    ]);
    
    try {
      $this->em->beginTransaction();
      
      $vendorResult = $this->validateVendor($ocrData);
      if ($vendorResult->isFailure()) {
        $this->em->rollback();
        return $vendorResult;
      }
      $vendor = $vendorResult->getData()['vendor'];
      
      $invoice = $this->createInvoiceHeader($ocrData, $vendor, $userId);
      $this->em->persist($invoice);
      
      $importMetadata = [
        'items_created' => 0,
        'items_matched' => 0,
        'upcs_stored' => 0,
        'line_errors' => [],
        'warnings' => [],
      ];

      $lineItemsResult = $this->processLineItems(
        $ocrData['line_items'] ?? [],
        $invoice,
        $importMetadata
      );
      
      if ($lineItemsResult->isFailure() && empty($invoice->getItems())) {
        $this->em->rollback();
        return $lineItemsResult;
      }

      $invoice->recalculateTotals();
      
      $this->annotateInvoiceWithImportInfo($invoice, $ocrData, $importMetadata);
      
      $this->em->flush();
      $this->em->commit();
      
      $this->logger->info('OCR import completed successfully', [
        'invoice_id' => $invoice->getId(),
        'line_items' => count($invoice->getItems()),
        'items_created' => $importMetadata['items_created'],
      ]);
      
      return ServiceResponse::success(
        data: [
          'invoice_id' => $invoice->getId(),
          'invoice' => $invoice,
          'metadata' => $importMetadata,
        ],
        message: sprintf(
          'Successfully imported %d line items (%d new items created)',
          count($invoice->getItems()),
          $importMetadata['items_created']
        )
            );
      
    } catch (\Throwable $e) {
      if ($this->em->getConnection()->isTransactionActive()) {
        $this->em->rollback();
      }
      
      $this->logger->error('OCR import failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      
      return ServiceResponse::failure(
        errors: ['System error: ' . $e->getMessage()],
        message: 'Failed to import receipt due to an unexpected error.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Validate and extract vendor from OCR data
   */
  private function validateVendor(array $ocrData): ServiceResponse
  {
    $vendorGuess = $ocrData['vendor_guess'] ?? null;
    
    if (!$vendorGuess || !isset($vendorGuess['vendor'])) {
      return ServiceResponse::failure(
        errors: ['Could not identify vendor from receipt'],
        message: 'Vendor identification failed'
      );
    }
    
    $vendor = $vendorGuess['vendor'];
    
    if (!$vendor instanceof Vendor) {
      return ServiceResponse::failure(
        errors: ['Invalid vendor object'],
        message: 'Vendor data is malformed'
      );
    }
    
    $confidence = $vendorGuess['confidence'] ?? 0;
    if ($confidence < 0.2) {
      $this->logger->warning('Low vendor confidence', [
        'confidence' => $confidence,
        'vendor_id' => $vendor->getId(),
        'candidates' => $vendorGuess['candidates'] ?? [],
      ]);
    }
    
    return ServiceResponse::success(
      data: [
        'vendor' => $vendor,
        'confidence' => $confidence,
        'alternatives' => $vendorGuess['candidates'] ?? [],
      ]
    );
  }
  
  /**
   * Create the invoice header in draft status
   */
  private function createInvoiceHeader(
    array $ocrData,
    Vendor $vendor,
    int $userId
  ): VendorInvoice {
    $invoice = new VendorInvoice();
        
    $invoice->setVendor($vendor);
    $invoice->setInvoiceNumber($ocrData['invoice_number'] ?? 'DRAFT-' . uniqid());
    
    $invoiceDate = $this->parseDate($ocrData['invoice_date']) ?? new \DateTime();
    $invoice->setInvoiceDate($invoiceDate);
        
    $dueDate = clone $invoiceDate;
    $paymentTerms = 30; # TODO: real paymetn terms
    $dueDate->modify("+{$paymentTerms} days");
    $invoice->setDueDate($dueDate);
    
    $invoice->setSubtotal((string)($ocrData['subtotal'] ?? 0));
    $invoice->setTaxAmount((string)($ocrData['tax'] ?? 0));
    $invoice->setTotalAmount((string)($ocrData['total'] ?? 0));
        
    $invoice->setStatus('draft');
    $invoice->setSourceType('ocr_scan');
    $invoice->setCreatedBy($userId);
    
    if (isset($ocrData['confidence_report']['avg'])) {
      $avgConf = $ocrData['confidence_report']['avg'] / 100;
      $invoice->setOcrConfidence(number_format($avgConf, 2));
    }
    
    return $invoice;
  }
  
  /**
   * Process all line items from OCR data
   */
  private function processLineItems(
    array $lineItems,
    VendorInvoice $invoice,
    array &$metadata
  ): ServiceResponse {
    if (empty($lineItems)) {
      return ServiceResponse::failure(
        errors: ['No line items found in OCR data'],
        message: 'Receipt contains no items'
      );
    }
    
    foreach ($lineItems as $index => $itemData) {
      try {
        $this->processLineItem($itemData, $invoice, $metadata);
      } catch (\Exception $e) {
        $error = sprintf(
          'Line %d: %s - %s',
          $index + 1,
          $itemData['name'] ?? 'Unknown',
          $e->getMessage(),
        );
        $metadata['line_errors'][] = $error;
        
        $this->logger->warning('Line item import failed', [
          'line' => $index + 1,
          'item_data' => $itemData,
          'error' => $e->getMessage(),
        ]);
        
        // Continue processing other items, we'd rather partial import
        continue;
      }
    }
    
    // Check if at least some items were successfully imported
    if (count($invoice->getItems()) === 0) {
      return ServiceResponse::failure(
        errors: array_merge(
          ['Failed to import any line items'],
          $metadata['line_errors']
        ),
        message: 'No items could be imported from receipt'
      );
    }
    
    return ServiceResponse::success(
      data: ['items_imported' => count($invoice->getItems())]
    );
  }
  
  /**
   * Process a single line item
   */
  private function processLineItem(
    array $itemData,
    VendorInvoice $invoice,
    array &$metadata
  ): void {

    $this->validateLineItemData($itemData);

    $stockTarget = $this->findOrCreateStockTarget($itemData, $metadata);

    $expenseAccount = $this->getDefaultExpenseAccount();

    $lineItem = new VendorInvoiceItem();
    $lineItem->setVendorInvoice($invoice);
    $lineItem->setDescription($itemData['name']);
    $lineItem->setQuantity((string)$itemData['qty']);
    
    $unitPrice = $itemData['unit_price'] 
    ?? ($itemData['ext_price'] / max($itemData['qty'], 1));
    $lineItem->setUnitPrice(number_format($unitPrice, 4, '.', ''));
    
    $lineItem->setLineTotal(number_format($itemData['ext_price'], 2, '.', ''));
    $lineItem->setStockTarget($stockTarget);
    $lineItem->setExpenseAccount($expenseAccount);
    
    if (!empty($itemData['uom'])) {
      $lineItem->setUnitOfMeasure($itemData['uom']);
    }
    
    $invoice->addItem($lineItem);
    $this->em->persist($lineItem);
  }

  /**
   * Find existing stock target by UPC or create new one
   */
  private function findOrCreateStockTarget(
    array $itemData,
    array &$metadata
  ): ?StockTarget {
    $upc = $itemData['upc'] ?? null;
        
    if ($upc) {
      $stockTarget = $this->findStockTargetByUPC($upc);
      if ($stockTarget) {
        $metadata['items_matched']++;
        return $stockTarget;
      }
    }

    $item = $this->createNewItem($itemData);
    $this->em->persist($item);
    
    $metadata['items_created']++;
    
    if ($upc) {
      $this->storeUPCForItem($item, $upc, $metadata);
    }

    $stockTarget = $this->createStockTarget($item);
    $this->em->persist($stockTarget);
    
    return $stockTarget;
  }

  /**
   * Find stock target by UPC code
   */
  private function findStockTargetByUPC(string $upc): ?StockTarget
  {
    $itemUpc = $this->itemUpcRepo->findOneBy(['barcode' => $upc]);
        
    if (!$itemUpc) {
      return null;
    }

    $item = $itemUpc->getItem();
    if (!$item) {
      return null;
    }
    
    return $this->stockTargetRepo->findOneBy(['item' => $item])
            ?? $this->createStockTarget($item);
  }

  /**
   * Create a new Item entity from OCR data
   */
  private function createNewItem(array $itemData): Item
  {
    $item = new Item();
    $item->setName($itemData['name']);
    $item->setCategory(self::DEFAULT_ITEM_CATEGORY);
    $item->setDescription(
      sprintf(
        'Auto-created from OCR import on %s',
        (new \DateTime())->format('Y-m-d H:i:s'),
      ));
    $item->setCreatedAt(new \DateTimeImmutable());
    $item->setUpdatedAt(new \DateTime());
    
    return $item;
  }

  /**
   * Create StockTarget for an Item
   */
  private function createStockTarget(Item $item): StockTarget
  {
    $stockTarget = new StockTarget();
    $stockTarget->setItem($item);
    $stockTarget->setName($item->getName());
    
    $baseUnit = $this->getDefaultUnit();
    if ($baseUnit) {
      $stockTarget->setBaseUnit($baseUnit);
    }
    
    return $stockTarget;
  }

  /**
   * Store UPC code for an item in item_variants table
   */
  private function storeUPCForItem(Item $item, string $upc, array &$metadata): void
  {
    $existing = $this->itemUpcRepo->findOneBy(['barcode' => $upc]);
    if ($existing) {
      return; // UPC already stored
    }
    
    $variant = new ItemVariant();
    $variant->setItem($item);
    $variant->setName($item->getName());
    $this->em->persist($variant);
    
    $itemUpc = new ItemUPC();
    $itemUpc->setItem($item);
    $itemUpc->setItemVariant($variant);
    $itemUpc->setBarcode($upc);
    
    $itemUpc->setQuantity('1.0000');
    $unit = $this->getDefaultUnit();
    if ($unit) {
      $itemUpc->setUnit($unit);
    }
    
    $this->em->persist($itemUpc);
    $metadata['upcs_stored']++;

    $this->logger->info('Stored UPC for new item', [
      'item_id' => $item->getId(),
      'upc' => $upc,
    ]);
  }

  /**
   * Validate line item has required data
   */
  private function validateLineItemData(array $itemData): void
  {
    if (empty($itemData['name'])) {
      throw new \InvalidArgumentException('Line item missing name/description');
    }
    
    if (!isset($itemData['qty']) || $itemData['qty'] <= 0) {
      throw new \InvalidArgumentException('Line item has invalid quantity');
    }
    
    if (!isset($itemData['ext_price'])) {
      throw new \InvalidArgumentException('Line item missing extended price');
    }
  }
  
  /**
   * Get or create default expense account
   */
  private function getDefaultExpenseAccount(): Account
  {
    $account = $this->accountRepo->findOneBy([
      'code' => self::DEFAULT_EXPENSE_ACCOUNT_CODE
    ]);
    
    if (!$account) {
      $this->logger->warning('Default expense account not found', [
        'code' => self::DEFAULT_EXPENSE_ACCOUNT_CODE
      ]);
      
      $account = $this->accountRepo->findOneBy(['type' => 'expense']);
            
      if (!$account) {
        throw new \RuntimeException(
          'No expense accounts configured in system. Please create account ' . 
            self::DEFAULT_EXPENSE_ACCOUNT_CODE
        );
      }
    }
    
    return $account;
  }

  /**
   * Get default unit for items
   */
  private function getDefaultUnit(): ?Unit
  {
    return $this->unitRepo->findOneBy(['name' => self::DEFAULT_UOM])
            ?? $this->unitRepo->findOneBy(['name' => 'each'])
            ?? $this->unitRepo->findOneBy([]) // Get any unit as fallback
            ?? null;
    }

  /**
   * Add detailed import notes to invoice
   */
  private function annotateInvoiceWithImportInfo(
    VendorInvoice $invoice,
    array $ocrData,
    array $metadata
  ): void {
    $notes = [];
        
    $vendorConf = $ocrData['vendor_guess']['confidence'] ?? 0;
    if ($vendorConf < 0.5) {
      $notes[] = sprintf(
        'Low vendor confidence (%.1f%%). Please verify vendor is correct.',
        $vendorConf * 100
      );
            
      // List alternative vendors if available
      $candidates = $ocrData['vendor_guess']['candidates'] ?? [];
      if (count($candidates) > 1) {
        $altVendors = array_slice($candidates, 1, 3); // Top 3 alternatives
        $vendorNames = array_map(function($c) {
                    $v = $this->vendorRepo->find($c['vendor_id']);
                    return $v ? $v->getName() : "ID:{$c['vendor_id']}";
                }, $altVendors);
        $notes[] = '   Alternative vendors: ' . implode(', ', $vendorNames);
      }
    }

    if ($metadata['items_created'] > 0) {
      $notes[] = sprintf(
        'Created %d new item(s) from this import.',
        $metadata['items_created']
      );
    }
    
    if ($metadata['upcs_stored'] > 0) {
      $notes[] = sprintf(
        'Stored %d UPC code(s) for future item matching.',
        $metadata['upcs_stored']
      );
    }

    $lowConfFields = $ocrData['confidence_report']['low_confidence_fields'] ?? [];
    if (!empty($lowConfFields)) {
      $notes[] = sprintf(
        '%d field(s) had low OCR confidence - please review carefully.',
        count($lowConfFields)
        );
    }
    
    // Add line errors if any
    if (!empty($metadata['line_errors'])) {
      $notes[] = 'Some line items could not be imported:';
      foreach (array_slice($metadata['line_errors'], 0, 5) as $error) {
        $notes[] = "   - $error";
      }
      if (count($metadata['line_errors']) > 5) {
        $notes[] = sprintf(
          '   ... and %d more errors',
          count($metadata['line_errors']) - 5
        );
      }
    }
    
    // Add import summary
    $notes[] = '';
    $notes[] = sprintf(
      'Imported on %s via OCR (avg confidence: %.1f%%)',
      (new \DateTime())->format('Y-m-d H:i:s'),
      $ocrData['confidence_report']['avg'] ?? 0
    );
    
    $invoice->setNotes(implode("\n", $notes));
  }

  /**
   * Parse date string from various formats
   */
  private function parseDate($dateString): ?\DateTime
  {
    # TODO: this should be a shared DateParser
    if (!$dateString) {
      return null;
    }
    
    if ($dateString instanceof \DateTime) {
      return $dateString;
    }
    
    try {
      $formats = [
        'Y-m-d',
        'm/d/Y',
        'd/m/Y',
        'Y-m-d H:i:s',
        'm/d/y',
        'd-m-Y',
      ];
      
      foreach ($formats as $format) {
        $date = \DateTime::createFromFormat($format, $dateString);
        if ($date !== false) {
          return $date;
        }
      }
      
      $timestamp = strtotime($dateString);
      if ($timestamp !== false) {
        return (new \DateTime())->setTimestamp($timestamp);
      }
    } catch (\Exception $e) {
      $this->logger->warning('Failed to parse date', [
        'date_string' => $dateString,
        'error' => $e->getMessage(),
      ]);
    }
    
    return null;
  }
}
