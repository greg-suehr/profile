<?php

namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\PurchaseItem;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\StockLot;
use App\Katzen\Entity\StockLotLocationBalance;
use App\Katzen\Entity\StockReceipt;
use App\Katzen\Entity\StockReceiptItem;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\PurchaseItemRepository;
use App\Katzen\Repository\StockLocationRepository;
use App\Katzen\Repository\StockReceiptRepository;
use App\Katzen\Repository\StockTransactionRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

final class StockReceiptService
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
        private PurchaseRepository $purchaseRepo,
        private PurchaseItemRepository $purchaseItemRepo,
        private StockReceiptRepository $receiptRepo,
        private StockLocationRepository $locationRepo,
        private StockTransactionRepository $txnRepo,
        private AccountingService $accounting,
    ) {}

    /**
     * Get the default receiving location
     */
    public function getDefaultLocation(): ?StockLocation
    {
        # TODO fetch from user preferences or system config
        return $this->locationRepo->findOneBy([]) ?? null;
    }

  /**
   * Generate a receipt number
   */
  private function generateReceiptNumber(): string
  {
    $latest = $this->receiptRepo->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

    $nextId = $latest ? ($latest->getId() + 1) : 1;
    return 'RCV-' . date('Y') . '-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
  }

  /**
   * Generate a lot number based on year and PO number
   */
  private function generateLotNumber(Purchase $purchase): string
  {
    $year = date('Y');
    $poNum = preg_replace('/[^A-Z0-9]/i', '', $purchase->getPoNumber());
    $sequence = str_pad((string)($purchase->getStockLots()->count() + 1), 3, '0', STR_PAD_LEFT);
        
    return "LOT-{$year}-{$poNum}-{$sequence}";
  }

  /**
   * Get open purchase orders available for receiving
   */
  public function getOpenPurchaseOrders(): array
  {
    return $this->purchaseRepo->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'partial'])
            ->orderBy('p.expected_delivery', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Create a new stock receipt from a purchase order
   * 
   * @param Purchase $purchase The purchase order to receive against
   * @param array $receiptData Receipt data including items array
   * @return ServiceResponse
   */
  public function createReceipt(Purchase $purchase, array $receiptData): ServiceResponse
  {
    try {
      $this->em->beginTransaction();
      
      // Validate purchase order
      if ($purchase->getStatus() === 'received') {
        return ServiceResponse::failure(
          errors: ['Purchase order already fully received'],
          message: 'Cannot receive against completed PO'
        );
      }
      
      // Create receipt
      $receipt = new StockReceipt();
      $receipt->setReceiptNumber($this->generateReceiptNumber());
      $receipt->setPurchase($purchase);
      $receipt->setReceivedAt($receiptData['received_at'] ?? new \DateTime());
      $receipt->setReceivedBy($this->security->getUser());
      $receipt->setNotes($receiptData['notes'] ?? null);

      // Set location
      $location = $receiptData['location'] ?? $this->getDefaultLocation();
      if (!$location) {
        throw new \RuntimeException('No receiving location available');
      }
      $receipt->setLocation($location);
      
      $totalAmount = '0.00';

      // Process each receipt item
      foreach ($receiptData['items'] as $itemData) {
        $receiptItem = $this->createReceiptItem($receipt, $itemData, $location);
        $receipt->addStockReceiptItem($receiptItem);
        $totalAmount = bcadd($totalAmount, $receiptItem->getLineTotal(), 2);
      }
      
      $receipt->setStatus('completed');

      $this->em->persist($receipt);
      $this->em->flush();

      // Record accounting entry
      $accountingResult = $this->accounting->recordEvent(
        templateName: 'stock_receipt',
        amounts: [
          ['expr_key' => 'receipt_total', 'amount' => (float)$totalAmount]
        ],
        referenceType: 'stock_receipt',
        referenceId: (string)$receipt->getId(),
        metadata: [
          'purchase_id' => $purchase->getId(),
          'receipt_number' => $receipt->getReceiptNumber(),
        ]
      );
      
      if (!$accountingResult->isSuccess()) {
        throw new \RuntimeException('Accounting entry failed: ' . $accountingResult->getMessage());
      }
      
      // Update purchase status if fully received
      $this->updatePurchaseStatus($purchase);
      
      $this->em->commit();
      
      return ServiceResponse::success(
        message: "Receipt {$receipt->getReceiptNumber()} created successfully",
        data: [
          'receipt_id' => $receipt->getId(),
          'receipt_number' => $receipt->getReceiptNumber(),
          'total_amount' => $totalAmount,
          'items_count' => count($receiptData['items']),
        ]
      );
      
    } catch (\Exception $e) {
      $this->em->rollback();
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to create receipt',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Create a receipt item with lot tracking
   */
  private function createReceiptItem(
    StockReceipt $receipt,
    array $itemData,
    StockLocation $location
  ): StockReceiptItem {
      $purchaseItem = $itemData['purchase_item'];
      $qtyReceived = $itemData['qty_received'];
      $unitCost = $purchaseItem->getUnitPrice();
      
      $receiptItem = new StockReceiptItem();
      $receiptItem->setStockReceipt($receipt);
      $receiptItem->addPurchaseItem($purchaseItem);
      $receiptItem->setStockTarget($purchaseItem->getStockTarget());
      $receiptItem->setQtyReceived($qtyReceived);
      $receiptItem->setQtyReturned('0.00');
      
      if (!empty($itemData['lot_number'])) {
        $receiptItem->setLotNumber($itemData['lot_number']);
      }
      if (!empty($itemData['expiration_date'])) {
        $receiptItem->setExpirationDate($itemData['expiration_date']);
      }
      
      $lot = $this->createStockLot($receiptItem, $receipt->getPurchase(), $location);
      $receiptItem->addStockLot($lot);
      
      $transaction = $this->createStockTransaction($receiptItem, $lot);
      $receiptItem->setStockTransaction($transaction);
      
      $currentReceived = $purchaseItem->getQtyReceived() ?? '0.00';
      $newReceived = bcadd($currentReceived, $qtyReceived, 2);
      $purchaseItem->setQtyReceived($newReceived);
      
      $stockTarget = $purchaseItem->getStockTarget();
      $currentQty = $stockTarget->getCurrentQty() ?? '0.00';
      $newQty = bcadd($currentQty, $qtyReceived, 2);
      $stockTarget->setCurrentQty($newQty);
      
      return $receiptItem;
  }
  
  /**
   * Create a StockLot for received inventory
   */
  private function createStockLot(
    StockReceiptItem $receiptItem,
    Purchase $purchase,
    StockLocation $location
  ): StockLot {
    $lot = new StockLot();
    $lot->setStockTarget($receiptItem->getStockTarget());
    $lot->setStockReceiptItem($receiptItem);
    $lot->setPurchase($purchase);
    $lot->setVendor($purchase->getVendor());
    
    $lotNumber = $receiptItem->getLotNumber() 
            ?? $this->generateLotNumber($purchase);
    $lot->setLotNumber($lotNumber);
    
    $lot->setReceivedDate($receiptItem->getStockReceipt()->getReceivedAt());
    $lot->setExpirationDate($receiptItem->getExpirationDate());
    
    // Set quantities
    $lot->setInitialQty($receiptItem->getQtyReceived());
    $lot->setCurrentQty($receiptItem->getQtyReceived());
    $lot->setReservedQty('0.00');

    // Set cost
    $lot->setUnitCost($receiptItem->getUnitCost());
    
    // Set timestamps
    $lot->setCreatedAt(new \DateTimeImmutable());
    $lot->setUpdatedAt(new \DateTime());
    
    // Create location balance
    $balance = new StockLotLocationBalance();
    $balance->setStockLot($lot);
    $balance->setLocation($location);
    $balance->setQty($receiptItem->getQtyReceived());
    $balance->setReservedQty('0.00');
    $balance->setUpdatedAt(new \DateTime());
    
    $lot->addLocationBalance($balance);
    $this->em->persist($balance);
    
    $this->em->persist($lot);
    
    return $lot;
  }

  /**
   * Create a StockTransaction for the receipt
   */
  private function createStockTransaction(
    StockReceiptItem $receiptItem,
    StockLot $lot
  ): StockTransaction {
    $transaction = new StockTransaction();
    $transaction->setStockTarget($receiptItem->getStockTarget());
    $transaction->setQty($receiptItem->getQtyReceived());
    $transaction->setUnitCost($receiptItem->getUnitCost());
    $transaction->setUseType('receipt');
    $transaction->setStatus('pending');
    $transaction->setReason('Stock received from PO: ' . $receiptItem->getStockReceipt()->getPurchase()->getPoNumber());   
    $transaction->setRecordedAt(new \DateTimeImmutable());
    $transaction->setEffectiveDate(new \DateTime());
    
    $transaction->setLotNumber($lot->getLotNumber());
    $transaction->setExpirationDate($lot->getExpirationDate());
    
    $this->em->persist($transaction);
    
    return $transaction;
  }

  /**
   * Update purchase order status based on received quantities
   */
  private function updatePurchaseStatus(Purchase $purchase): void
  {
    $allReceived = true;
        
    foreach ($purchase->getPurchaseItems() as $item) {
      $ordered = (float)$item->getQtyOrdered();
      $received = (float)($item->getQtyReceived() ?? '0.00');
      
      if ($received < $ordered) {
        $allReceived = false;
        break;
      }
    }
    
    if ($allReceived) {
      $purchase->setStatus('received');
      $purchase->setReceivedAt(new \DateTime());
    } elseif ($purchase->getStatus() === 'pending') {
      $purchase->setStatus('partial');
    }
    
    $purchase->setUpdatedAt(new \DateTime());
  }
  
  # TODO: delete StockReceiptService->receiveStock as soon as possible                                                   
  /**
   * NOTE: please don't use this. Use StockReceiptService->createReceipt, which tracks lots, which you need.
   * Receive stock against a purchase order.
   *
   * Creates StockTransactions with unit_cost populated (for use by CostingService),
   * updates inventory levels, updates purchase item quantities, and records accounting entries.
   *
   * @param int $purchaseId The purchase order ID
   * @param array $items Array of ['purchase_item_id' => int, 'qty_received' => float, 'unit_cost' => float]
   * @param \DateTimeInterface|null $receivedDate The receipt date (defaults to now)
   * @param string|null $notes Optional notes for the receipt
   * @return ServiceResponse Success with receipt details; failure on validation errors
   */
  public function receiveStock(
     int $purchaseId, 
    array $items, 
      ?\DateTimeInterface $receivedDate = null,
      ?string $notes = null
  ): ServiceResponse
  {
    try {
      $purchase = $this->purchaseRepo->find($purchaseId);
      
      if (!$purchase) {
                return ServiceResponse::failure(
                  errors: ['Purchase order not found.'],
                  message: 'Stock receipt failed.'
                );
      }
      
      // Validate purchase order status
      if ($purchase->getStatus() === 'cancelled' || $purchase->getStatus() === 'rejected') {
        return ServiceResponse::failure(
          errors: ["Cannot receive stock for {$purchase->getStatus()} purchase order."],
          message: 'Stock receipt failed.'
        );
      }
      
      if (empty($items)) {
        return ServiceResponse::failure(
          errors: ['No items provided for receipt.'],
          message: 'Stock receipt failed.'
        );
      }
      
      $now = new \DateTime();
      $receiptDate = $receivedDate ?? $now;
      $totalValue = 0.0;
      $transactionsCreated = [];
      
      foreach ($items as $itemData) {
        $purchaseItem = $this->purchaseItemRepo->find($itemData['purchase_item_id']);
        
        if (!$purchaseItem || $purchaseItem->getPurchase()->getId() !== $purchaseId) {
          return ServiceResponse::failure(
            errors: ['Invalid purchase item ID.'],
            message: 'Stock receipt failed.'
          );
        }
        
        $stockTarget = $purchaseItem->getStockTarget();
        
        if (!$stockTarget) {
          return ServiceResponse::failure(
            errors: ['Purchase item must have an associated stock target.'],
            message: 'Stock receipt failed.'
          );
        }
        
        $qtyReceived = (float)$itemData['qty_received'];
        $unitCost = (float)$itemData['unit_cost'];
        
        // Validate quantities
        if ($qtyReceived <= 0) {
          return ServiceResponse::failure(
            errors: ['Received quantity must be positive.'],
            message: 'Stock receipt failed.'
          );
        }
        
        // Check if receiving more than ordered
        $qtyOrdered = (float)$purchaseItem->getQtyOrdered();
        $currentReceived = (float)$purchaseItem->getQtyReceived();
        $newTotalReceived = $currentReceived + $qtyReceived;
        
        if ($newTotalReceived > $qtyOrdered) {
          return ServiceResponse::failure(
            errors: ["Cannot receive more than ordered. Ordered: {$qtyOrdered}, Already received: {$currentReceived}, Attempting: {$qtyReceived}"],
            message: 'Stock receipt failed.'
          );
        }
        
        // Create stock transaction with unit_cost for CostingService
        $transaction = new StockTransaction();
        $transaction->setStockTarget($stockTarget);
        $transaction->setQty((string)$qtyReceived);
        $transaction->setUnitCost((string)$unitCost); // CRITICAL for CostingService
        $transaction->setUseType('receipt');
        $transaction->setReason($notes ?? "Goods receipt from PO {$purchase->getPoNumber()}");
        $transaction->setEffectiveDate($receiptDate);
        $transaction->setRecordedAt(new \DateTimeImmutable());
        $transaction->setStatus('completed');
        $transaction->setUnit($stockTarget->getBaseUnit());
        
        // Update purchase item received quantity
        $purchaseItem->setQtyReceived((string)$newTotalReceived);
        
        // Update stock target quantity
        $currentQty = (float)$stockTarget->getCurrentQty();
        $newQty = $currentQty + $qtyReceived;
        $stockTarget->setCurrentQty((string)$newQty);
        
        $this->txnRepo->save($transaction, false);
        
        $lineValue = $qtyReceived * $unitCost;
        $totalValue += $lineValue;
        
        $transactionsCreated[] = [
          'stock_target_id' => $stockTarget->getId(),
          'stock_target' => $stockTarget->getName(),
          'qty_received' => $qtyReceived,
          'unit_cost' => $unitCost,
          'line_value' => $lineValue,
          'new_qty' => $newQty,
          'transaction_id' => $transaction->getId(),
        ];
      }
      
      $this->em->flush();
      
      // Update purchase order status
      $allReceived = $this->checkIfPurchaseFullyReceived($purchase);
      if ($allReceived) {
        $purchase->setStatus('received');
      } else {
        $purchase->setStatus('partially_received');
      }
      $purchase->setUpdatedAt($now);
      $this->purchaseRepo->save($purchase, true);
      
      // Record accounting entry for goods receipt
      $accountingResult = $this->accounting->recordEvent(
        templateName: 'goods_receipt',
        amounts: [
          'gr_total' => $totalValue,
        ],
        referenceType: 'purchase',
        referenceId: (string)$purchase->getId(),
        metadata: [
          'po_number' => $purchase->getPoNumber(),
          'receipt_date' => $receiptDate->format('Y-m-d'),
        ]
      );
      
      if ($accountingResult->isFailure()) {
        // Log but don't fail the receipt
        error_log("Failed to record accounting entry for PO {$purchase->getPoNumber()}: " 
                  . $accountingResult->getFirstError());
      }
      
      return ServiceResponse::success(
        data: [
          'purchase_id' => $purchase->getId(),
          'po_number' => $purchase->getPoNumber(),
          'receipt_date' => $receiptDate->format('Y-m-d'),
          'total_value' => $totalValue,
          'transactions' => $transactionsCreated,
          'purchase_status' => $purchase->getStatus(),
        ],
        message: 'Stock received successfully.'
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to receive stock: ' . $e->getMessage()],
        message: 'Unhandled exception while receiving stock.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Get receipt status for a purchase order.
   *
   * Returns details about what has been received vs ordered for each line item.
   *
   * @param int $purchaseId The purchase order ID
   * @return ServiceResponse Success with receipt status; failure if not found
   */
  public function getReceiptStatus(int $purchaseId): ServiceResponse
  {
    try {
      $purchase = $this->purchaseRepo->find($purchaseId);
      
      if (!$purchase) {
        return ServiceResponse::failure(
          errors: ['Purchase order not found.'],
          message: 'Receipt status retrieval failed.'
        );
      }
      
      $items = [];
      $fullyReceived = true;
      
      foreach ($purchase->getPurchaseItems() as $item) {
        $qtyOrdered = (float)$item->getQtyOrdered();
        $qtyReceived = (float)$item->getQtyReceived();
        $qtyRemaining = $qtyOrdered - $qtyReceived;
        
        if ($qtyRemaining > 0) {
          $fullyReceived = false;
        }
        
        $items[] = [
          'purchase_item_id' => $item->getId(),
          'stock_target' => $item->getStockTarget()?->getName(),
          'qty_ordered' => $qtyOrdered,
          'qty_received' => $qtyReceived,
          'qty_remaining' => $qtyRemaining,
          'unit_price' => (float)$item->getUnitPrice(),
        ];
      }
      
      return ServiceResponse::success(
        data: [
          'purchase_id' => $purchase->getId(),
          'po_number' => $purchase->getPoNumber(),
          'status' => $purchase->getStatus(),
          'fully_received' => $fullyReceived,
          'items' => $items,
        ],
        message: 'Receipt status retrieved successfully.'
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to retrieve receipt status: ' . $e->getMessage()],
        message: 'Unhandled exception while retrieving receipt status.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Check if all items on a purchase order have been fully received.
   *
   * @param Purchase $purchase The purchase order to check
   * @return bool True if all items fully received; false otherwise
   */
  private function checkIfPurchaseFullyReceived(Purchase $purchase): bool
  {
    foreach ($purchase->getPurchaseItems() as $item) {
      $qtyOrdered = (float)$item->getQtyOrdered();
      $qtyReceived = (float)$item->getQtyReceived();
      
      if ($qtyReceived < $qtyOrdered) {
        return false;
      }
    }
    
    return true;
  }
}
