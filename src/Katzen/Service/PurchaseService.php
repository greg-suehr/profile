<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\PurchaseItem;
use App\Katzen\Repository\PurchaseRepository;
use App\Katzen\Repository\PurchaseItemRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class PurchaseService
{
  public function __construct(
    private EntityManagerInterface $em,
    private PurchaseRepository $purchaseRepo,
    private PurchaseItemRepository $purchaseItemRepo,
    private AccountingService $accounting,
  ) {}

  /**
   * Create a new purchase order with line items.
   *
   * Validates the purchase order, calculates totals, and persists
   * the Purchase entity along with its PurchaseItem children.
   *
   * @param Purchase $purchase The purchase order to create
   * @return ServiceResponse Success with purchase_id; failure on validation errors
     */
  public function createPurchaseOrder(Purchase $purchase): ServiceResponse
  {
    try {
      if ($purchase->getPurchaseItems()->isEmpty()) {
        return ServiceResponse::failure(
          errors: ['Purchase order must have at least one line item.'],
          message: 'Purchase order creation failed.'
        );
      }

      $now = new \DateTime();
      $purchase->setReceivedAt($now);
      $purchase->setUpdatedAt($now);
      
      if (!$purchase->getStatus()) {
        $purchase->setStatus('pending');
      }

      $subtotal = 0.0;
      foreach ($purchase->getPurchaseItems() as $item) {
        $lineTotal = (float)$item->getQtyOrdered() * (float)$item->getUnitPrice();
        $item->setLineTotal((string)$lineTotal);
        $item->setQtyReceived('0.00');
        $subtotal += $lineTotal;
      }
      
      $purchase->setSubtotal((string)$subtotal);
      
      $taxAmount = (float)($purchase->getTaxAmount() ?? '0.00');
      $totalAmount = $subtotal + $taxAmount;
      $purchase->setTotalAmount((string)$totalAmount);
      
      if (!$purchase->getPoNumber()) {
        $purchase->setPoNumber($this->generatePoNumber());
      }
      
      $this->purchaseRepo->save($purchase, true);

      return ServiceResponse::success(
        data: [
          'purchase_id' => $purchase->getId(),
          'po_number' => $purchase->getPoNumber(),
          'subtotal' => $subtotal,
          'tax_amount' => $taxAmount,
          'total_amount' => $totalAmount,
          'line_items' => count($purchase->getPurchaseItems()),
        ],
        message: 'Purchase order created successfully.'
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to create purchase order: ' . $e->getMessage()],
        message: 'Unhandled exception while creating purchase order.',
        metadata: ['exception' => get_class($e)]
      );
    }    
  }

  /**
   * Update the status of a purchase order.
   *
   * Valid statuses: draft, submitted, approved, rejected, cancelled, received, closed
   *
   * @param int $purchaseId The purchase order ID
   * @param string $status The new status
   * @return ServiceResponse Success with updated purchase; failure on errors
   */
  public function updatePurchaseStatus(int $purchaseId, string $status): ServiceResponse
  {
    try {
      $purchase = $this->purchaseRepo->find($purchaseId);
      
      if (!$purchase) {
        return ServiceResponse::failure(
          errors: ['Purchase order not found.'],
          message: 'Status update failed.'
        );
      }
      
      $validStatuses = ['draft', 'submitted', 'approved', 'rejected', 'cancelled', 'received', 'closed'];
      if (!in_array($status, $validStatuses)) {
        return ServiceResponse::failure(
          errors: ["Invalid status. Must be one of: " . implode(', ', $validStatuses)],
          message: 'Status update failed.'
        );
      }
      
      $purchase->setStatus($status);
      $purchase->setUpdatedAt(new \DateTime());
      
      $this->purchaseRepo->save($purchase, true);
      
      return ServiceResponse::success(
        data: [
          'purchase_id' => $purchase->getId(),
          'po_number' => $purchase->getPoNumber(),
          'status' => $status,
        ],
        message: "Purchase order status updated to '{$status}'."
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to update purchase status: ' . $e->getMessage()],
        message: 'Unhandled exception while updating purchase status.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Get a purchase order by ID with all related data.
   *
   * @param int $purchaseId The purchase order ID
   * @return ServiceResponse Success with purchase data; failure if not found
   */
  public function getPurchaseOrder(int $purchaseId): ServiceResponse
  {
    try {
      $purchase = $this->purchaseRepo->find($purchaseId);
      
      if (!$purchase) {
        return ServiceResponse::failure(
          errors: ['Purchase order not found.'],
          message: 'Purchase order retrieval failed.'
        );
      }
      
      $items = [];
      foreach ($purchase->getPurchaseItems() as $item) {
        $items[] = [
          'id' => $item->getId(),
          'stock_target' => $item->getStockTarget()?->getName(),
          'qty_ordered' => (float)$item->getQtyOrdered(),
          'qty_received' => (float)$item->getQtyReceived(),
          'unit_price' => (float)$item->getUnitPrice(),
          'line_total' => (float)$item->getLineTotal(),
        ];
      }
      
      return ServiceResponse::success(
        data: [
          'id' => $purchase->getId(),
          'po_number' => $purchase->getPoNumber(),
          'order_date' => $purchase->getOrderDate()?->format('Y-m-d'),
          'expected_delivery' => $purchase->getExpectedDelivery()?->format('Y-m-d'),
          'status' => $purchase->getStatus(),
          'subtotal' => (float)$purchase->getSubtotal(),
          'tax_amount' => (float)$purchase->getTaxAmount(),
          'total_amount' => (float)$purchase->getTotalAmount(),
          'items' => $items,
        ],
        message: 'Purchase order retrieved successfully.'
      );
      
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to retrieve purchase order: ' . $e->getMessage()],
        message: 'Unhandled exception while retrieving purchase order.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }
  
  /**
   * Generate a sequential PO number.
   *
   * Uses the highest existing purchase ID to compute the next available number,
   * formatted as `PO-YYYY-XXXXXX`.
   *
   * @return string Formatted PO number
   */
  private function generatePoNumber(): string
  {
    $latest = $this->purchaseRepo->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    
    $nextId = $latest ? ($latest->getId() + 1) : 1;
    return 'PO-' . date('Y') . '-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
  }
}
