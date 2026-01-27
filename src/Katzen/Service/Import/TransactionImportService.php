<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\PurchaseItem;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Repository\SellableRepository;
use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\StateMachine\OrderStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Transaction Import Service
 * 
 * Handles importing transactional records (Orders, Purchases, etc.) from
 * denormalized import data. The key innovation is **transaction grouping**:
 * 
 * Import files typically have one row per line item:
 * ```
 * transaction_id, product,    qty, price, customer,  date
 * TXN001,         Latte,      2,   4.50,  John,      2024-01-15
 * TXN001,         Croissant,  1,   3.50,  John,      2024-01-15
 * TXN002,         Espresso,   1,   3.00,  Jane,      2024-01-15
 * ```
 * 
 * This service groups these into proper transactions:
 * - Order TXN001 with 2 OrderItems (Latte x2, Croissant x1) for John
 * - Order TXN002 with 1 OrderItem (Espresso x1) for Jane
 * 
 * The grouping strategy:
 * 1. Identify grouping key (transaction_id, order_id, receipt_no)
 * 2. Group rows by this key
 * 3. For each group:
 *    a. Extract order-level data from first row (date, customer, location)
 *    b. Create Order entity (or find existing)
 *    c. Create OrderItem for each row in group
 *    d. Recalculate totals
 * 
 * @example Usage:
 * ```php
 * $result = $transactionImporter->processGroupedTransactions(
 *     $rows,
 *     $mapping,
 *     $batch,
 *     $entityMap, // For resolving sellables, customers, etc.
 * );
 * ```
 */
final class TransactionImportService
{
    /**
     * Customer resolution strategies for imported orders.
     */
    public const CUSTOMER_STRATEGY_HISTORICAL = 'historical';
    public const CUSTOMER_STRATEGY_PER_LOCATION = 'per_location';
    public const CUSTOMER_STRATEGY_ANONYMOUS = 'anonymous';
    public const CUSTOMER_STRATEGY_MATCH = 'match';
    public const CUSTOMER_STRATEGY_CREATE = 'create';
    
    /**
     * Default customer name for historical imports.
     */
    private const HISTORICAL_CUSTOMER_NAME = 'Historical Import';
    
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private ?OrderRepository $orderRepo = null,
        private ?SellableRepository $sellableRepo = null,
    ) {
        // Lazy-load repositories if not injected
        $this->orderRepo ??= $em->getRepository(Order::class);
        $this->sellableRepo ??= $em->getRepository(Sellable::class);
    }
    
    // ========================================================================
    // NEW: Grouped Transaction Processing
    // ========================================================================
    
    /**
     * Process transactions using grouping strategy.
     * 
     * This is the main entry point for transactional imports. It:
     * 1. Identifies the grouping key column
     * 2. Groups rows by transaction
     * 3. Creates Order + OrderItems for each group
     * 
     * @param array $rows All import rows
     * @param ImportMapping $mapping Field mapping configuration
     * @param int $batchId Import batch ID for tracking
     * @param EntityMap|null $entityMap Pre-populated map of master data entities
     * @param array $options {
     *   @type string $customer_strategy How to handle customers (default: 'match')
     *   @type string $order_status Initial order status (default: 'completed')
     *   @type bool $aggregate_duplicates Combine rows with same product (default: true)
     *   @type string|null $default_location Default location if not in data
     * }
     * @return ServiceResponse
     */
    public function processGroupedTransactions(
        array $rows,
        ImportMapping $mapping,
        int $batchId,
        ?EntityMap $entityMap = null,
        array $options = []
    ): ServiceResponse {
        $entityMap ??= new EntityMap();
        $customerStrategy = $options['customer_strategy'] ?? self::CUSTOMER_STRATEGY_MATCH;
        $orderStatus = $options['order_status'] ?? 'completed';
        $aggregateDuplicates = $options['aggregate_duplicates'] ?? true;
        
        $entityType = $mapping->getEntityType();
        
        // Only handle order/order_item types
        if (!in_array($entityType, ['order', 'order_line', 'order_item'])) {
            return $this->processNonGroupedTransactions($rows, $mapping, $batchId, $entityMap);
        }
        
        // Step 1: Detect grouping key
        $groupingKey = $this->detectGroupingKey($rows, $mapping);
        if (!$groupingKey) {
            $this->logger->warning('No grouping key detected, falling back to row-by-row processing');
            return $this->processNonGroupedTransactions($rows, $mapping, $batchId, $entityMap);
        }
        
        $this->logger->info('Using grouping key for transactions', [
            'grouping_key' => $groupingKey,
            'total_rows' => count($rows),
        ]);
        
        // Step 2: Group rows by transaction
        $grouped = $this->groupRowsByKey($rows, $groupingKey, $aggregateDuplicates, $mapping);
        
        $this->logger->info('Rows grouped into transactions', [
            'transaction_count' => count($grouped),
            'original_rows' => count($rows),
        ]);
        
        // Step 3: Process each transaction group
        $results = [
            'orders_created' => 0,
            'orders_updated' => 0,
            'order_items_created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        
        foreach ($grouped as $transactionId => $lineItems) {
            $result = $this->processTransactionGroup(
                $transactionId,
                $lineItems,
                $mapping,
                $batchId,
                $entityMap,
                $customerStrategy,
                $orderStatus
            );
            
            if ($result->isSuccess()) {
                if ($result->data['order_created'] ?? false) {
                    $results['orders_created']++;
                } else {
                    $results['orders_updated']++;
                }
                $results['order_items_created'] += $result->data['items_created'] ?? 0;
            } else {
                $results['skipped']++;
                $results['errors'][] = [
                    'transaction_id' => $transactionId,
                    'errors' => $result->errors,
                ];
            }
        }
        
        return ServiceResponse::success(
            data: [
                'entity_type' => 'order',
                'entity_counts' => [
                    'orders' => $results['orders_created'] + $results['orders_updated'],
                    'order_items' => $results['order_items_created'],
                ],
                'details' => $results,
            ],
            message: sprintf(
                'Created %d orders with %d items (%d errors)',
                $results['orders_created'],
                $results['order_items_created'],
                count($results['errors'])
            )
        );
    }
    
    /**
     * Detect the best column to use as grouping key.
     * 
     * Looks for transaction identifiers in the mapped fields.
     */
    private function detectGroupingKey(array $rows, ImportMapping $mapping): ?string
    {
        $fieldMappings = $mapping->getFieldMappings();
        
        // Priority order for grouping key detection
        $candidates = [
            'transaction_id',
            'order_id',
            'order_number',
            'receipt_no',
            'sale_id',
            'invoice_number',
        ];
        
        // Check field mappings first
        foreach ($candidates as $candidate) {
            $column = $this->findColumnForField($candidate, $fieldMappings);
            if ($column !== null) {
                return $column;
            }
        }
        
        // Check raw headers for candidate columns
        if (!empty($rows)) {
            $firstRow = $rows[0];
            $headers = array_keys($firstRow);
            
            foreach ($candidates as $candidate) {
                foreach ($headers as $header) {
                    $normalized = $this->normalizeHeader($header);
                    if ($normalized === $candidate || str_contains($normalized, $candidate)) {
                        return $header;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Group rows by the specified key column.
     * 
     * Optionally aggregates duplicate products within the same transaction.
     */
    private function groupRowsByKey(
        array $rows,
        string $keyColumn,
        bool $aggregateDuplicates,
        ImportMapping $mapping
    ): array {
        $grouped = [];
        
        foreach ($rows as $row) {
            $transactionId = trim($row[$keyColumn] ?? '');
            if (empty($transactionId)) {
                $transactionId = '_orphan_' . ($row['_row_number'] ?? uniqid());
            }
            
            if (!isset($grouped[$transactionId])) {
                $grouped[$transactionId] = [];
            }
            
            $grouped[$transactionId][] = $row;
        }
        
        // Optionally aggregate duplicates
        if ($aggregateDuplicates) {
            foreach ($grouped as $transactionId => $lineItems) {
                $grouped[$transactionId] = $this->aggregateLineItems($lineItems, $mapping);
            }
        }
        
        return $grouped;
    }
    
    /**
     * Aggregate duplicate products within a transaction.
     * 
     * If the same product appears multiple times, sum the quantities.
     */
    private function aggregateLineItems(array $lineItems, ImportMapping $mapping): array
    {
        $fieldMappings = $mapping->getFieldMappings();
        
        $productColumn = $this->findColumnForField('sellable', $fieldMappings)
            ?? $this->findColumnForField('product', $fieldMappings)
            ?? $this->findColumnForField('item', $fieldMappings)
            ?? $this->findColumnForField('product_name', $fieldMappings)
            ?? $this->findColumnForField('product_detail', $fieldMappings);
        
        $qtyColumn = $this->findColumnForField('quantity', $fieldMappings)
            ?? $this->findColumnForField('qty', $fieldMappings)
            ?? $this->findColumnForField('transaction_qty', $fieldMappings);
        
        if (!$productColumn) {
            return $lineItems; // Can't aggregate without product identification
        }
        
        $aggregated = [];
        
        foreach ($lineItems as $row) {
            $productKey = strtolower(trim($row[$productColumn] ?? ''));
            
            if (empty($productKey)) {
                $aggregated[] = $row;
                continue;
            }
            
            if (!isset($aggregated[$productKey])) {
                $aggregated[$productKey] = $row;
            } else {
                // Sum quantities
                $existingQty = (float) ($aggregated[$productKey][$qtyColumn] ?? 1);
                $newQty = (float) ($row[$qtyColumn] ?? 1);
                $aggregated[$productKey][$qtyColumn] = $existingQty + $newQty;
                
                // Keep first row's other data, but track aggregation
                $aggregated[$productKey]['_aggregated_rows'] = 
                    ($aggregated[$productKey]['_aggregated_rows'] ?? 1) + 1;
            }
        }
        
        return array_values($aggregated);
    }
    
    /**
     * Process a single transaction group.
     * 
     * Creates one Order with multiple OrderItems.
     */
    private function processTransactionGroup(
        string $transactionId,
        array $lineItems,
        ImportMapping $mapping,
        int $batchId,
        EntityMap $entityMap,
        string $customerStrategy,
        string $orderStatus
    ): ServiceResponse {
        if (empty($lineItems)) {
            return ServiceResponse::failure(['No line items in transaction']);
        }
        
        $fieldMappings = $mapping->getFieldMappings();
        $firstRow = $lineItems[0];
        
        // Check for existing order with this transaction ID
        $existingOrder = $this->findExistingOrder($transactionId);
        $orderCreated = false;
        
        if ($existingOrder) {
            $order = $existingOrder;
        } else {
            // Create new Order from first row
            $order = $this->createOrderFromRow($firstRow, $transactionId, $mapping, $entityMap, $customerStrategy);
            $order->setStatus($orderStatus);
            $this->em->persist($order);
            $orderCreated = true;
        }
        
        // Create OrderItems for each line
        $itemsCreated = 0;
        $errors = [];
        
        foreach ($lineItems as $index => $row) {
            $itemResult = $this->createOrderItemFromRow($order, $row, $mapping, $entityMap);
            
            if ($itemResult->isSuccess()) {
                $itemsCreated++;
            } else {
                $errors[] = sprintf('Line %d: %s', $index + 1, implode(', ', $itemResult->errors));
            }
        }
        
        // Recalculate totals
        $this->recalculateOrderTotals($order);
        
        if (!empty($errors) && $itemsCreated === 0) {
            return ServiceResponse::failure($errors);
        }
        
        return ServiceResponse::success([
            'order_id' => $order->getId(),
            'transaction_id' => $transactionId,
            'order_created' => $orderCreated,
            'items_created' => $itemsCreated,
            'errors' => $errors,
        ]);
    }
    
    /**
     * Create an Order entity from the first row of a transaction group.
     */
    private function createOrderFromRow(
        array $row,
        string $transactionId,
        ImportMapping $mapping,
        EntityMap $entityMap,
        string $customerStrategy
    ): Order {
        $data = $this->transform($row, $mapping);
        
        $order = new Order();
        
        // Set transaction ID as order number for traceability
        $order->setOrderNumber($transactionId);
        
        // Resolve customer based on strategy
        $customer = $this->resolveCustomerForOrder($data, $entityMap, $customerStrategy);
        if ($customer) {
            $order->setCustomerEntity($customer);
        } elseif ($data['customer'] ?? $data['customer_name'] ?? null) {
            $order->setCustomer($data['customer'] ?? $data['customer_name']);
        }
        
        // Resolve location
        $locationRef = $data['location'] ?? $data['store_location'] ?? $data['store'] ?? null;
        if ($locationRef && $entityMap->has('stock_location', $locationRef)) {
            $location = $entityMap->getEntity('stock_location', $locationRef, $this->em);
            if ($location) {
                $order->setLocation($location);
            }
        }
        
        // Parse and set order date
        $orderDate = $this->combineDateTime(
            $data['transaction_date'] ?? $data['order_date'] ?? $data['date'] ?? null,
            $data['transaction_time'] ?? $data['order_time'] ?? $data['time'] ?? null
        );
        
        if ($orderDate) {
            $order->setScheduledAt($orderDate);
        }
        
        // Set notes if present
        if ($data['notes'] ?? $data['order_notes'] ?? null) {
            $order->setNotes($data['notes'] ?? $data['order_notes']);
        }
        
        // Track import metadata
        $order->setImportBatchId($mapping->getId()); // Using mapping ID as batch reference
        $order->setExternalId($transactionId);
        
        return $order;
    }
    
    /**
     * Create an OrderItem entity from a row.
     */
    private function createOrderItemFromRow(
        Order $order,
        array $row,
        ImportMapping $mapping,
        EntityMap $entityMap
    ): ServiceResponse {
        $data = $this->transform($row, $mapping);
        
        // Resolve sellable
        $sellableRef = $data['sellable'] ?? $data['product'] ?? $data['product_name'] 
            ?? $data['product_detail'] ?? $data['item'] ?? null;
        
        if (!$sellableRef) {
            return ServiceResponse::failure(['Product/sellable reference is required']);
        }
        
        // Try EntityMap first, then database lookup
        $sellable = null;
        if ($entityMap->has('sellable', $sellableRef)) {
            $sellable = $entityMap->getEntity('sellable', $sellableRef, $this->em);
        }
        
        if (!$sellable) {
            $sellable = $this->resolveSellable($sellableRef);
        }
        
        if (!$sellable) {
            return ServiceResponse::failure(["Sellable not found: {$sellableRef}"]);
        }
        
        // Parse quantity
        $quantity = $data['quantity'] ?? $data['qty'] ?? $data['transaction_qty'] ?? 1;
        $quantity = (float) $quantity;
        if ($quantity <= 0) {
            return ServiceResponse::failure(['Quantity must be positive']);
        }
        
        // Parse price (use import price or fall back to sellable base price)
        $unitPrice = $data['unit_price'] ?? $data['price'] ?? $data['selling_price'] ?? null;
        if ($unitPrice !== null) {
            $unitPrice = (string) $this->parseNumber($unitPrice);
        } else {
            $unitPrice = $sellable->getBasePrice() ?? '0.00';
        }
        
        // Create OrderItem
        $orderItem = new OrderItem();
        $orderItem->setOrder($order);
        $orderItem->setSellable($sellable);
        $orderItem->setQuantity($quantity);
        $orderItem->setUnitPrice($unitPrice);
        
        // Calculate line total
        $lineTotal = (float) $unitPrice * $quantity;
        $orderItem->setLineTotal((string) $lineTotal);
        
        // Optional variant
        $variantRef = $data['variant'] ?? $data['size'] ?? $data['option'] ?? null;
        if ($variantRef && $entityMap->has('sellable_variant', $variantRef)) {
            $variant = $entityMap->getEntity('sellable_variant', $variantRef, $this->em);
            if ($variant) {
                $orderItem->setVariant($variant);
            }
        }
        
        // Notes
        if ($data['notes'] ?? $data['item_notes'] ?? $data['line_notes'] ?? null) {
            $orderItem->setNotes($data['notes'] ?? $data['item_notes'] ?? $data['line_notes']);
        }
        
        $this->em->persist($orderItem);
        $order->addOrderItem($orderItem);
        
        return ServiceResponse::success([
            'sellable_id' => $sellable->getId(),
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }
    
    /**
     * Resolve customer based on strategy.
     */
    private function resolveCustomerForOrder(
        array $data,
        EntityMap $entityMap,
        string $strategy
    ): ?Customer {
        $customerRef = $data['customer'] ?? $data['customer_name'] ?? $data['customer_id'] ?? null;
        
        switch ($strategy) {
            case self::CUSTOMER_STRATEGY_HISTORICAL:
                return $this->findOrCreateCustomer(self::HISTORICAL_CUSTOMER_NAME, null);
                
            case self::CUSTOMER_STRATEGY_PER_LOCATION:
                $locationRef = $data['location'] ?? $data['store_location'] ?? $data['store'] ?? 'Unknown';
                return $this->findOrCreateCustomer("Store: {$locationRef}", null);
                
            case self::CUSTOMER_STRATEGY_ANONYMOUS:
                return null; // No customer association
                
            case self::CUSTOMER_STRATEGY_MATCH:
                if (!$customerRef) {
                    return null;
                }
                // Try EntityMap first
                if ($entityMap->has('customer', $customerRef)) {
                    return $entityMap->getEntity('customer', $customerRef, $this->em);
                }
                // Try database lookup
                return $this->resolveCustomer($customerRef);
                
            case self::CUSTOMER_STRATEGY_CREATE:
                if (!$customerRef) {
                    return null;
                }
                return $this->findOrCreateCustomer($customerRef, $data['email'] ?? null);
                
            default:
                return null;
        }
    }
    
    /**
     * Find an existing order by transaction ID.
     */
    private function findExistingOrder(string $transactionId): ?Order
    {
        return $this->orderRepo->findOneBy(['external_id' => $transactionId])
            ?? $this->orderRepo->findOneBy(['order_number' => $transactionId]);
    }
    
    /**
     * Combine date and time fields into a single DateTime.
     */
    private function combineDateTime(mixed $date, mixed $time = null): ?\DateTimeInterface
    {
        if (!$date) {
            return null;
        }
        
        $dateObj = $this->parseDate($date, null);
        if (!$dateObj) {
            return null;
        }
        
        if ($time) {
            $timeStr = trim((string) $time);
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $timeStr, $matches)) {
                $hours = (int) $matches[1];
                $minutes = (int) $matches[2];
                $seconds = (int) ($matches[3] ?? 0);
                $dateObj->setTime($hours, $minutes, $seconds);
            }
        }
        
        return $dateObj;
    }
    
    /**
     * Find or create a customer by name.
     */
    private function findOrCreateCustomer(string $name, ?string $email): Customer
    {
        $repo = $this->em->getRepository(Customer::class);
        
        $existing = null;
        if ($email) {
            $existing = $repo->findOneBy(['email' => $email]);
        }
        if (!$existing) {
            $existing = $repo->findOneBy(['name' => $name]);
        }
        
        if ($existing) {
            return $existing;
        }
        
        $customer = new Customer();
        $customer->setName($name);
        if ($email) {
            $customer->setEmail($email);
        }
        $customer->setStatus('active');
        
        $this->em->persist($customer);
        
        return $customer;
    }
    
    // ========================================================================
    // LEGACY: Non-grouped transaction processing (row-by-row)
    // Kept for backward compatibility and non-order entity types
    // ========================================================================
    
    /**
     * Process transactions without grouping (row-by-row).
     * 
     * Used for entity types that don't require grouping (purchases, single items, etc.)
     * or when no grouping key can be detected.
     */
    private function processNonGroupedTransactions(
        array $rows,
        ImportMapping $mapping,
        int $batchId,
        EntityMap $entityMap
    ): ServiceResponse {
        $entityType = $mapping->getEntityType();
        
        $counts = [];
        $errors = [];
        
        foreach ($rows as $row) {
            $result = $this->importTransaction($row, $mapping, $batchId);
            
            if ($result->isSuccess()) {
                $type = $result->data['entity_type'] ?? 'unknown';
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            } else {
                $errors[] = [
                    'row' => $row['_row_number'] ?? '?',
                    'errors' => $result->errors,
                ];
            }
        }
        
        return ServiceResponse::success([
            'entity_type' => $entityType,
            'entity_counts' => $counts,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 100), // Cap error details
        ]);
    }
    
    /**
     * Import a single transaction row (legacy method).
     */
    public function importTransaction(
        array $row,
        ImportMapping $mapping,
        int $batchId
    ): ServiceResponse {
        $entityType = $mapping->getEntityType();
        try {
            return match ($entityType) {
                'order', 'order_line' => $this->importOrderLine($row, $mapping, $batchId),
                'purchase', 'purchase_line' => $this->importPurchaseLine($row, $mapping, $batchId),
                'item' => $this->importItem($row, $mapping, $batchId),
                'sellable' => $this->importSellable($row, $mapping, $batchId),
                'customer' => $this->importCustomer($row, $mapping, $batchId),
                'vendor' => $this->importVendor($row, $mapping, $batchId),
                default => ServiceResponse::failure(["Unknown entity type: {$entityType}"]),
            };
        } catch (\Throwable $e) {
            return ServiceResponse::failure([$e->getMessage()]);
        }
    }
    
    // ========================================================================
    // EXISTING: Transform and Helper Methods
    // ========================================================================
    
    /**
     * Transform row data using mapping rules.
     */
    public function transform(array $row, ImportMapping $mapping): array
    {
        $fieldMappings = $mapping->getFieldMappings();
        $transformationRules = $mapping->getTransformationRules() ?? [];
        $defaultValues = $mapping->getDefaultValues() ?? [];
        
        $transformed = [];
        
        foreach ($fieldMappings as $column => $config) {
            $targetField = is_array($config) 
                ? ($config['target_field'] ?? $config['field'] ?? $column) 
                : $config;
            
            $value = $row[$column] ?? null;
            
            if (isset($transformationRules[$column])) {
                $value = $this->applyTransformation($value, $transformationRules[$column]);
            }
            
            $transformed[$targetField] = $value;
        }
        
        foreach ($defaultValues as $field => $default) {
            if (!isset($transformed[$field]) || $transformed[$field] === null || $transformed[$field] === '') {
                $transformed[$field] = $default;
            }
        }
        
        return $transformed;
    }
    
    private function applyTransformation(mixed $value, array|string $rule): mixed
    {
        if (is_string($rule)) {
            $rule = ['type' => $rule];
        }
        
        $type = $rule['type'] ?? 'string';
        
        return match ($type) {
            'lowercase' => strtolower((string) $value),
            'uppercase' => strtoupper((string) $value),
            'trim' => trim((string) $value),
            'number', 'float' => $this->parseNumber($value),
            'integer' => (int) $this->parseNumber($value),
            'date' => $this->parseDate($value, $rule['format'] ?? null),
            'datetime' => $this->parseDateTime($value, $rule['format'] ?? null),
            'boolean' => $this->parseBoolean($value),
            'currency' => number_format($this->parseNumber($value), 2, '.', ''),
            'map' => $rule['mapping'][$value] ?? $value,
            default => $value,
        };
    }
    
    private function parseNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $cleaned = preg_replace('/[^\d.-]/', '', (string) $value);
        return (float) $cleaned;
    }
    
    private function parseDate(mixed $value, ?string $format = null): ?\DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return $value instanceof \DateTime ? $value : \DateTime::createFromInterface($value);
        }
        
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }
        
        if ($format) {
            $date = \DateTime::createFromFormat($format, $stringValue);
            if ($date !== false) {
                return $date;
            }
        }
        
        $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $date = \DateTime::createFromFormat($fmt, $stringValue);
            if ($date !== false) {
                return $date;
            }
        }
        
        $timestamp = strtotime($stringValue);
        return $timestamp !== false ? (new \DateTime())->setTimestamp($timestamp) : null;
    }
    
    private function parseDateTime(mixed $value, ?string $format = null): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }
        
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'm/d/Y H:i:s', 'm/d/Y H:i'];
        foreach ($formats as $fmt) {
            $date = \DateTime::createFromFormat($fmt, $stringValue);
            if ($date !== false) {
                return $date;
            }
        }
        
        $timestamp = strtotime($stringValue);
        return $timestamp !== false ? (new \DateTime())->setTimestamp($timestamp) : null;
    }
    
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $lower = strtolower(trim((string) $value));
        return in_array($lower, ['true', 'yes', '1', 'y', 't', 'on'], true);
    }
    
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
    
    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        return preg_replace('/[\s\-\.]+/', '_', $normalized);
    }
    
    private function recalculateOrderTotals(Order $order): void
    {
        $subtotal = 0.0;
        foreach ($order->getOrderItems() as $item) {
            $subtotal += (float) $item->getLineTotal();
        }
        $order->setSubtotal((string) $subtotal);
        $order->calculateTotals();
    }
    
    private function recalculatePurchaseTotals(Purchase $purchase): void
    {
        $total = 0.0;
        foreach ($purchase->getPurchaseItems() as $item) {
            $total += (float) $item->getLineTotal();
        }
        $purchase->setTotal((string) $total);
    }
    
    // ========================================================================
    // Entity Resolution Helpers
    // ========================================================================
    
    private function resolveSellable(string $ref): ?Sellable
    {
        return $this->sellableRepo->findOneBy(['sku' => $ref])
            ?? $this->sellableRepo->findOneBy(['name' => $ref]);
    }
    
    private function resolveItem(string $ref): ?object
    {
        $repo = $this->em->getRepository(\App\Katzen\Entity\Item::class);
        return $repo->findOneBy(['sku' => $ref]) ?? $repo->findOneBy(['name' => $ref]);
    }
    
    private function resolveCustomer(string $ref): ?Customer
    {
        $repo = $this->em->getRepository(Customer::class);
        if (filter_var($ref, FILTER_VALIDATE_EMAIL)) {
            $found = $repo->findOneBy(['email' => $ref]);
            if ($found) {
                return $found;
            }
        }
        return $repo->findOneBy(['name' => $ref]);
    }
    
    private function resolveVendor(string $ref): ?object
    {
        return $this->em->getRepository(\App\Katzen\Entity\Vendor::class)->findOneBy(['name' => $ref]);
    }
    
    private function resolveOrder(string $ref): ?Order
    {
        return $this->orderRepo->findOneBy(['order_number' => $ref]);
    }
    
    private function resolvePurchase(string $ref): ?Purchase
    {
        return $this->em->getRepository(Purchase::class)->findOneBy(['purchase_number' => $ref]);
    }
    
    // ========================================================================
    // LEGACY: Individual import methods (kept for backward compatibility)
    // ========================================================================
    
    private function importOrderLine(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $sellableRef = $data['sellable'] ?? $data['product'] ?? $data['item'] ?? null;
        if (!$sellableRef) {
            return ServiceResponse::failure(['Sellable/product reference is required']);
        }
        
        $sellable = $this->resolveSellable($sellableRef);
        if (!$sellable) {
            return ServiceResponse::failure(["Sellable not found: {$sellableRef}"]);
        }
        
        $orderRef = $data['order_number'] ?? $data['order_id'] ?? null;
        $order = $orderRef ? $this->resolveOrder($orderRef) : null;
        
        $quantity = $data['quantity'] ?? $data['qty'] ?? 1;
        if ($quantity <= 0) {
            return ServiceResponse::failure(['Quantity must be positive']);
        }
        
        $unitPrice = $data['unit_price'] ?? $data['price'] ?? null;
        
        if (!$order) {
            $order = $this->createOrderLegacy($data, $batchId);
        }
        
        $orderItem = new OrderItem();
        $orderItem->setOrder($order);
        $orderItem->setSellable($sellable);
        $orderItem->setQuantity((float) $quantity);
        $orderItem->setUnitPrice($unitPrice !== null ? (string) $unitPrice : ($sellable->getBasePrice() ?? '0.00'));
        
        $lineTotal = (float) $orderItem->getUnitPrice() * (float) $quantity;
        $orderItem->setLineTotal((string) $lineTotal);
        
        if ($data['notes'] ?? null) {
            $orderItem->setNotes($data['notes']);
        }
        
        $this->em->persist($orderItem);
        $this->recalculateOrderTotals($order);
        
        return ServiceResponse::success([
            'entity_type' => 'order_item',
            'order_id' => $order->getId(),
        ]);
    }
    
    private function createOrderLegacy(array $data, int $batchId): Order
    {
        $order = new Order();
        
        $customerRef = $data['customer'] ?? $data['customer_name'] ?? null;
        if ($customerRef) {
            $customer = $this->resolveCustomer($customerRef);
            if ($customer) {
                $order->setCustomerEntity($customer);
            } else {
                $order->setCustomer($customerRef);
            }
        }
        
        $orderDate = $data['order_date'] ?? $data['date'] ?? null;
        if ($orderDate) {
            $parsed = $this->parseDate($orderDate, null);
            if ($parsed) {
                $order->setScheduledAt($parsed);
            }
        }
        
        $this->em->persist($order);
        return $order;
    }
    
    private function importPurchaseLine(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $itemRef = $data['item'] ?? $data['product'] ?? null;
        if (!$itemRef) {
            return ServiceResponse::failure(['Item reference is required']);
        }
        
        $item = $this->resolveItem($itemRef);
        if (!$item) {
            return ServiceResponse::failure(["Item not found: {$itemRef}"]);
        }
        
        $vendorRef = $data['vendor'] ?? $data['supplier'] ?? null;
        $vendor = $vendorRef ? $this->resolveVendor($vendorRef) : null;
        
        $purchaseRef = $data['purchase_number'] ?? $data['po_number'] ?? null;
        $purchase = $purchaseRef ? $this->resolvePurchase($purchaseRef) : null;
        
        if (!$purchase) {
            if (!$vendor) {
                return ServiceResponse::failure(['Vendor is required to create a new purchase']);
            }
            $purchase = $this->createPurchase($data, $vendor, $batchId);
        }
        
        $quantity = $data['quantity'] ?? $data['qty'] ?? 1;
        if ($quantity <= 0) {
            return ServiceResponse::failure(['Quantity must be positive']);
        }
        
        $unitCost = $data['unit_cost'] ?? $data['cost'] ?? $data['unit_price'] ?? '0.00';
        
        $purchaseItem = new PurchaseItem();
        $purchaseItem->setPurchase($purchase);
        $purchaseItem->setItem($item);
        $purchaseItem->setQuantity((float) $quantity);
        $purchaseItem->setUnitCost((string) $unitCost);
        $purchaseItem->setLineTotal((string) ((float) $unitCost * (float) $quantity));
        
        $this->em->persist($purchaseItem);
        $this->recalculatePurchaseTotals($purchase);
        
        return ServiceResponse::success([
            'entity_type' => 'purchase_item',
            'purchase_id' => $purchase->getId(),
        ]);
    }
    
    private function createPurchase(array $data, object $vendor, int $batchId): Purchase
    {
        $purchase = new Purchase();
        $purchase->setVendor($vendor);
        
        $purchaseDate = $data['purchase_date'] ?? $data['date'] ?? null;
        if ($purchaseDate) {
            $parsed = $this->parseDate($purchaseDate, null);
            if ($parsed) {
                $purchase->setPurchaseDate($parsed);
            }
        }
        
        $this->em->persist($purchase);
        return $purchase;
    }
    
    private function importItem(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $name = $data['name'] ?? null;
        if (!$name) {
            return ServiceResponse::failure(['Item name is required']);
        }
        
        $existing = $this->resolveItem($data['sku'] ?? $name);
        if ($existing) {
            if ($data['description'] ?? null) {
                $existing->setDescription($data['description']);
            }
            return ServiceResponse::success(['entity_type' => 'item', 'item_id' => $existing->getId(), 'was_updated' => true]);
        }
        
        $item = new \App\Katzen\Entity\Item();
        $item->setName(trim($name));
        if ($data['sku'] ?? null) {
            $item->setSku($data['sku']);
        }
        if ($data['description'] ?? null) {
            $item->setDescription($data['description']);
        }
        
        $this->em->persist($item);
        
        return ServiceResponse::success(['entity_type' => 'item', 'was_created' => true]);
    }
    
    private function importSellable(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $name = $data['name'] ?? null;
        if (!$name) {
            return ServiceResponse::failure(['Sellable name is required']);
        }
        
        $existing = $this->resolveSellable($data['sku'] ?? $name);
        if ($existing) {
            if ($data['description'] ?? null) {
                $existing->setDescription($data['description']);
            }
            if (isset($data['price'])) {
                $existing->setBasePrice((string) $data['price']);
            }
            return ServiceResponse::success(['entity_type' => 'sellable', 'sellable_id' => $existing->getId(), 'was_updated' => true]);
        }
        
        $sellable = new Sellable();
        $sellable->setName(trim($name));
        if ($data['sku'] ?? null) {
            $sellable->setSku($data['sku']);
        }
        if (isset($data['price'])) {
            $sellable->setBasePrice((string) $data['price']);
        }
        
        $this->em->persist($sellable);
        
        return ServiceResponse::success(['entity_type' => 'sellable', 'was_created' => true]);
    }
    
    private function importCustomer(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $name = $data['name'] ?? $data['customer_name'] ?? null;
        if (!$name) {
            return ServiceResponse::failure(['Customer name is required']);
        }
        
        $existing = $this->resolveCustomer($data['email'] ?? $name);
        if ($existing) {
            if ($data['phone'] ?? null) {
                $existing->setPhone($data['phone']);
            }
            return ServiceResponse::success(['entity_type' => 'customer', 'was_updated' => true]);
        }
        
        $customer = new Customer();
        $customer->setName(trim($name));
        if ($data['email'] ?? null) {
            $customer->setEmail($data['email']);
        }
        if ($data['phone'] ?? null) {
            $customer->setPhone($data['phone']);
        }
        
        $this->em->persist($customer);
        
        return ServiceResponse::success(['entity_type' => 'customer', 'was_created' => true]);
    }
    
    private function importVendor(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
    {
        $data = $this->transform($row, $mapping);
        
        $name = $data['name'] ?? $data['vendor_name'] ?? null;
        if (!$name) {
            return ServiceResponse::failure(['Vendor name is required']);
        }
        
        $existing = $this->resolveVendor($name);
        if ($existing) {
            if ($data['phone'] ?? null) {
                $existing->setPhone($data['phone']);
            }
            return ServiceResponse::success(['entity_type' => 'vendor', 'was_updated' => true]);
        }
        
        $vendor = new \App\Katzen\Entity\Vendor();
        $vendor->setName(trim($name));
        if ($data['email'] ?? null) {
            $vendor->setEmail($data['email']);
        }
        
        $this->em->persist($vendor);
        
        return ServiceResponse::success(['entity_type' => 'vendor', 'was_created' => true]);
    }
}
