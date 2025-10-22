<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\StockCount;
use App\Katzen\Entity\StockCountItem;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Repository\StockCountRepository;
use App\Katzen\Repository\StockCountItemRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Repository\StockTransactionRepository;
use App\Katzen\Service\Response\ServiceResponse;

final class InventoryService
{
    public function __construct(
      private StockCountRepository $countRepo,
      private StockCountItemRepository $countItemRepo,      
      private StockTargetRepository $targetRepo,
      private StockTransactionRepository $txnRepo,
    ) {}

  /**
   * Add stock to a specific StockTarget and record a transaction.
   *
   * Increments the target’s current quantity by `$qty` and persists a
   * `StockTransaction` with useType `addition`. Returns the new on-hand
   * quantity and delta applied.
   *
   * * @param int $stockTargetId The StockTarget ID to receive stock
   * * @param float $qty Positive quantity to add
   * * @param ?string $reason Optional human-readable reason/memo
   * * @return ServiceResponse Success with new quantity; failure on validation/errors
   */
  public function addStock(int $stockTargetId, float $qty, ?string $reason = null): ServiceResponse
  {
    try {
      if ($qty <= 0) {
        return ServiceResponse::failure(
          errors: ['Quantity must be positive.'],
          message: 'Add stock failed.'
        );
      }
      
      $target = $this->requireTarget($stockTargetId);
    
      $transaction = new StockTransaction();
      $transaction->setStockTarget($target);
      $transaction->setQty($qty);
      $transaction->setReason($reason);
      $transaction->setUseType('addition');
    
      $target->setCurrentQty($target->getCurrentQty() + $qty);
    
      $this->txnRepo->save($transaction);

      return ServiceResponse::success(
        data: [
          'stock_target_id' => $stockTargetId,
          'delta'           => $qty,
          'new_qty'         => (float)$target->getCurrentQty(),
        ],
        message: 'Stock added.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to add stock: ' . $e->getMessage()],
        message: 'Unhandled exception while adding stock.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Set an absolute on-hand quantity for a StockTarget (adjustment).
   *
   * Computes the delta from the current quantity to `$newQty`, writes a
   * `StockTransaction` with useType `adjustment`, and updates the target’s
   * current quantity.
   *
   * * @param int $stockTargetId The StockTarget ID to adjust
   * * @param float $newQty New non-negative absolute quantity
   * * @param ?string $reason Optional human-readable reason/memo
   * * @return ServiceResponse Success with old/new/delta; failure on validation/errors
   */
  public function adjustStock(int $stockTargetId, float $newQty, ?string $reason = null): ServiceResponse
  {
    try {
      if ($newQty < 0) {
        return ServiceResponse::failure(
          errors: ['New quantity cannot be negative.'],
          message: 'Adjust stock failed.'
        );
      }
      
      $target = $this->requireTarget($stockTargetId);
    
      $oldQty = $target->getCurrentQty();
      $delta = $newQty - $oldQty;
      
      if ($delta === 0.0) {
        return ServiceResponse::success(
          data: ['stock_target_id' => $stockTargetId, 'old_qty' => $oldQty, 'new_qty' => $newQty, 'delta' => 0.0],
          message: 'No change.'
        );
      }
    
      $transaction = new StockTransaction();
      $transaction->setStockTarget($target);
      $transaction->setQty($delta);
      $transaction->setReason($reason);
      $transaction->setUseType('adjustment');
      
      $target->setCurrentQty($newQty);
      
      $this->txnRepo->save($transaction);

      return ServiceResponse::success(
        data: ['stock_target_id' => $stockTargetId, 'old_qty' => $oldQty, 'new_qty' => $newQty, 'delta' => $delta],
        message: 'Stock adjusted.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to adjust stock: ' . $e->getMessage()],
        message: 'Unhandled exception while adjusting stock.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Consume stock from a StockTarget and record a transaction.
   *
   * Decrements on-hand by `$quantity` (must be positive), persisting a
   * `StockTransaction` with negative qty, useType `consumption`, and timestamps.
   * Fails if consumption would drive quantity below zero.
   *
   * * @param int $stockTargetId The StockTarget ID to consume from
   * * @param float $quantity Positive quantity to consume
   * * @param ?string $reason Optional human-readable reason/memo
   * * @return ServiceResponse Success with new quantity; failure on insufficiency/errors
   */
  public function consumeStock(int $stockTargetId, float $quantity, ?string $reason = null): ServiceResponse
  {
    try {
      if ($quantity <= 0) {
        return ServiceResponse::failure(
          errors: ['Quantity must be positive.'],
          message: 'Consume stock failed.'
        );
      }

      $target = $this->requireTarget($stockTargetId);
      $newQty = $target->getCurrentQty() - $quantity;
    
      if ($newQty < 0) {
        # TODO: check retryability on message queue
        return ServiceResponse::failure(
          errors: [sprintf('Insufficient stock: need %.3f more.', abs($newQty))],
          message: 'Consume stock failed.',
          data: ['stock_target_id' => $stockTargetId, 'available' => (float)$target->getCurrentQty()]
        );
      }
      
      $transaction = new StockTransaction();
      $transaction->setStockTarget($target);
      $transaction->setQty(-$quantity);
      $transaction->setReason($reason);
      $transaction->setUseType('consumption');
      $transaction->setEffectiveDate(new \DateTime); 
      $transaction->setRecordedAt(new \DateTimeImmutable);
      $transaction->setStatus('pending');
      $transaction->setStockTarget($target);
      
      $target->setCurrentQty($newQty);
      
      $this->txnRepo->save($transaction);
      
      return ServiceResponse::success(
        data: [
          'stock_target_id' => $stockTargetId,
          'delta'           => -$quantity,
          'new_qty'         => $newQty,
        ],
        message: 'Stock consumed.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to consume stock: ' . $e->getMessage()],
        message: 'Unhandled exception while consuming stock.',
        metadata: ['exception' => get_class($e)]
      );
    }    
  }

  /**
   * Check availability for multiple StockTargets in bulk.
   *
   * For each requested target, compares current quantity to needed quantity and
   * reports `ok` or `insufficient`, along with computed shortage/surplus.
   *
   * * @param array<int,float> $itemQuantities Map of stockTargetId => qtyNeeded
   * * @return ServiceResponse On success: data['results'] per target and data['is_short'] flag
   */
  public function bulkCheckStock(array $itemQuantities): ServiceResponse
  {
    try {
      $results = [];
      $isShort = false;

      foreach ($itemQuantities as $stockTargetId => $qtyNeeded) {
        $target = $this->requireTarget($stockTargetId);
        $currentQty = (float) $target->getCurrentQty();
        $remainingQty = $currentQty - $qtyNeeded;
        
        if ($remainingQty >= 0) {
          $statusLabel = "ok";
        }
        else {
          $statusLabel = "insufficient";
          $isShort = true;
        }

        $results[$stockTargetId] = [
          "name"     => $target->getName(),
          "unit"     => $target->getBaseUnit() ? $target->getBaseUnit()->getName() : "unitless",         
          "quantity" => $currentQty,
          "status"   => $statusLabel,
          "shortage" => max(-$remainingQty, 0),
          "surplus"  => max($remainingQty, 0),
        ];
      }

      return ServiceResponse::success(
        data: ['results' => $results, 'is_short' => $isShort],
        message: $isShort ? 'Insufficient stock for one or more items.' : 'All stock available'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to check stock: ' . $e->getMessage()],
        message: 'Unhandled exception while checking stock.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Record a bulk stock count with per-target items and reconcile on-hand.
   *
   * Creates a `StockCount` and `StockCountItem` records for each provided target,
   * writes `manual_count` transactions, and sets each StockTarget’s current
   * quantity to the counted amount.
   *
   * * @param array<int,float> $countedQuantities Map of stockTargetId => countedQty
   * * @param ?string $notes Optional notes to attach to the StockCount
   * * @return ServiceResponse Success with count ID and item count; failure with details
   */
  public function recordBulkStockCount(array $countedQuantities, ?string $notes = ''): ServiceResponse
  {
    try {
      $now = new \DateTime();
      $count = new StockCount();
      $count->setTimestamp($now);
      $count->setNotes($notes ?? '');

      $items = 0;
      foreach ($countedQuantities as $stockTargetId => $countedQty) {
        $target = $this->requireTarget($stockTargetId);
        $expectedQty = (float) $target->getCurrentQty();
        
        $item = new StockCountItem();
        $item->setStockCount($count);
        $item->setStockTarget($target);
        $item->setExpectedQty($expectedQty);
        $item->setCountedQty($countedQty);
        $item->setUnit($target->getBaseUnit()); # TODO: support contextual unit counts
        $item->setNotes('Bulk manual count entry');
      
        $count->addStockCountItem($item);
        $this->countItemRepo->add($item);

        $txn = new StockTransaction();
        $txn->setStockTarget($target);
        $txn->setQty($countedQty);
        $txn->setUseType('manual_count');
        $txn->setUnit($target->getBaseUnit());
        $txn->setReason('Manual stock count from bulk entry at ' . $now->format('Y-m-d H:i'));
      
        $this->txnRepo->add($txn);
        // TODO: consider whether to defer setCurrentQty to Estimator service
        $target->setCurrentQty($countedQty);
        ++$items;
      }

      // Bulk flush persists staged CountItems and Transactions
      $this->countRepo->save($count);

      return ServiceResponse::success(
        data: [
          'count_id'   => $count->getId(),
          'item_count' => $items,
        ],
        message: 'Bulk stock count recorded.'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: ['Failed to record bulk count: ' . $e->getMessage()],
        message: 'Unhandled exception while recording bulk stock count.',
        metadata: ['exception' => get_class($e)]
      );
    }
  }

  /**
   * Require and return a StockTarget by ID or throw if missing.
   *
   * * @param int $id StockTarget ID to load
   * * @return StockTarget The found target
   * * @throws \RuntimeException If no target exists for the given ID
   */
  private function requireTarget(int $id): StockTarget
  {
    $target = $this->targetRepo->find($id);
    if (!$target) {
      throw new \RuntimeException("Stock target not found for: $id");
    }
    return $target;
  }
}
