<?php

namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\PurchaseItem;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\PurchaseItemRepository;
use App\Katzen\Repository\StockTransactionRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class StockReceiptService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockTransactionRepository $txnRepo,
        private PurchaseRepository $purchaseRepo,
        private PurchaseItemRepository $purchaseItemRepo,
        private AccountingService $accounting,
    ) {}

    /**
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
