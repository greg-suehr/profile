<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Transaction Import Service
 * 
 * Handles importing individual transactional records (Orders, Purchases, etc.)
 * after master data has been established.
 */
final class TransactionImportService
{
  public function __construct(
    private EntityManagerInterface $em,
    private LoggerInterface $logger,
  ) {}
  
  /**
   * Import a single transaction row
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

  /**
   * Transform row data using mapping rules
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
  
  private function parseDate(mixed $value, ?string $format = null): ?\DateTimeInterface
  {
    if ($value instanceof \DateTimeInterface) return $value;
    
    $stringValue = trim((string) $value);
    if ($stringValue === '') return null;
    
    if ($format) {
      $date = \DateTime::createFromFormat($format, $stringValue);
      if ($date !== false) return $date;
    }
    
    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
      $date = \DateTime::createFromFormat($fmt, $stringValue);
      if ($date !== false) return $date;
    }
    
    $timestamp = strtotime($stringValue);
    return $timestamp !== false ? (new \DateTime())->setTimestamp($timestamp) : null;
  }

  private function parseDateTime(mixed $value, ?string $format = null): ?\DateTimeInterface
  {
    if ($value instanceof \DateTimeInterface) return $value;
        
    $stringValue = trim((string) $value);
    if ($stringValue === '') return null;
    
    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'm/d/Y H:i:s', 'm/d/Y H:i'];
    foreach ($formats as $fmt) {
      $date = \DateTime::createFromFormat($fmt, $stringValue);
      if ($date !== false) return $date;
    }
    
    $timestamp = strtotime($stringValue);
    return $timestamp !== false ? (new \DateTime())->setTimestamp($timestamp) : null;
  }
  
  private function parseBoolean(mixed $value): bool
  {
    if (is_bool($value)) return $value;
    $lower = strtolower(trim((string) $value));
    return in_array($lower, ['true', 'yes', '1', 'y', 't', 'on'], true);
  }

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
      $order = $this->createOrder($data, $batchId);
    }
    
    $orderItem = new \App\Katzen\Entity\OrderItem();
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
    
    $purchaseItem = new \App\Katzen\Entity\PurchaseItem();
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

  private function importItem(array $row, ImportMapping $mapping, int $batchId): ServiceResponse
  {
    $data = $this->transform($row, $mapping);
    
    $name = $data['name'] ?? null;
    if (!$name) {
      return ServiceResponse::failure(['Item name is required']);
    }
    
    $existing = $this->resolveItem($data['sku'] ?? $name);
    if ($existing) {
      if ($data['description'] ?? null) $existing->setDescription($data['description']);
      return ServiceResponse::success(['entity_type' => 'item', 'item_id' => $existing->getId(), 'was_updated' => true]);
    }
    
    $item = new \App\Katzen\Entity\Item();
    $item->setName(trim($name));
    if ($data['sku'] ?? null) $item->setSku($data['sku']);
    if ($data['description'] ?? null) $item->setDescription($data['description']);
    
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
      if ($data['description'] ?? null) $existing->setDescription($data['description']);
      if (isset($data['price'])) $existing->setBasePrice((string) $data['price']);
      return ServiceResponse::success(['entity_type' => 'sellable', 'sellable_id' => $existing->getId(), 'was_updated' => true]);
    }
    
    $sellable = new \App\Katzen\Entity\Sellable();
    $sellable->setName(trim($name));
    if ($data['sku'] ?? null) $sellable->setSku($data['sku']);
    if (isset($data['price'])) $sellable->setBasePrice((string) $data['price']);
    
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
      if ($data['phone'] ?? null) $existing->setPhone($data['phone']);
      return ServiceResponse::success(['entity_type' => 'customer', 'was_updated' => true]);
    }
    
    $customer = new \App\Katzen\Entity\Customer();
    $customer->setName(trim($name));
    if ($data['email'] ?? null) $customer->setEmail($data['email']);
    if ($data['phone'] ?? null) $customer->setPhone($data['phone']);
    
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
      if ($data['phone'] ?? null) $existing->setPhone($data['phone']);
      return ServiceResponse::success(['entity_type' => 'vendor', 'was_updated' => true]);
    }
    
    $vendor = new \App\Katzen\Entity\Vendor();
    $vendor->setName(trim($name));
    if ($data['email'] ?? null) $vendor->setEmail($data['email']);
    
    $this->em->persist($vendor);
    
    return ServiceResponse::success(['entity_type' => 'vendor', 'was_created' => true]);
  }

  private function resolveSellable(string $ref): ?object
  {
    $repo = $this->em->getRepository(\App\Katzen\Entity\Sellable::class);
    return $repo->findOneBy(['sku' => $ref]) ?? $repo->findOneBy(['name' => $ref]);
  }
    
  private function resolveItem(string $ref): ?object
  {
    $repo = $this->em->getRepository(\App\Katzen\Entity\Item::class);
    return $repo->findOneBy(['sku' => $ref]) ?? $repo->findOneBy(['name' => $ref]);
  }
  
  private function resolveCustomer(string $ref): ?object
  {
    $repo = $this->em->getRepository(\App\Katzen\Entity\Customer::class);
    if (filter_var($ref, FILTER_VALIDATE_EMAIL)) {
      $found = $repo->findOneBy(['email' => $ref]);
      if ($found) return $found;
    }
    return $repo->findOneBy(['name' => $ref]);
  }
    
  private function resolveVendor(string $ref): ?object
  {
    return $this->em->getRepository(\App\Katzen\Entity\Vendor::class)->findOneBy(['name' => $ref]);
  }
    
  private function resolveOrder(string $ref): ?object
  {
    return $this->em->getRepository(\App\Katzen\Entity\Order::class)->findOneBy(['order_number' => $ref]);
  }
    
  private function resolvePurchase(string $ref): ?object
  {
    return $this->em->getRepository(\App\Katzen\Entity\Purchase::class)->findOneBy(['purchase_number' => $ref]);
  }

  private function createOrder(array $data, int $batchId): object
  {
    $order = new \App\Katzen\Entity\Order();
        
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
      if ($parsed) $order->setScheduledAt($parsed);
    }
    
    $this->em->persist($order);
    return $order;
   }

  private function createPurchase(array $data, object $vendor, int $batchId): object
  {
    $purchase = new \App\Katzen\Entity\Purchase();
    $purchase->setVendor($vendor);
    
    $purchaseDate = $data['purchase_date'] ?? $data['date'] ?? null;
    if ($purchaseDate) {
      $parsed = $this->parseDate($purchaseDate, null);
      if ($parsed) $purchase->setPurchaseDate($parsed);
    }
    
    $this->em->persist($purchase);
    return $purchase;
  }
  
  private function recalculateOrderTotals(object $order): void
  {
    $subtotal = 0;
    foreach ($order->getOrderItems() as $item) {
      $subtotal += (float) $item->getLineTotal();
    }
    $order->setSubtotal((string) $subtotal);
  }
  
  private function recalculatePurchaseTotals(object $purchase): void
  {
    $total = 0;
    foreach ($purchase->getPurchaseItems() as $item) {
      $total += (float) $item->getLineTotal();
    }
    $purchase->setTotal((string) $total);
  }
}
