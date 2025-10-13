<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\StockCount;
use App\Katzen\Entity\StockCountItem;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Service\Response\ServiceResponse;
use App\Katzen\Repository\StockTargetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryService
{
    public function __construct(
        private StockTargetRepository $targetRepo,
        private EntityManagerInterface $em,
    ) {}

  public function addStock(int $stockTargetId, float $qty, ?string $reason = null): void
  {
    $target = $this->requireTarget($stockTargetId);
    
    $transaction = new StockTransaction();
    $transaction->setStockTarget($target);
    $transaction->setQty($qty);
    $transaction->setReason($reason);
    $transaction->setUseType('addition');
    
    $target->setCurrentQty($target->getCurrentQty() + $qty);
    
    $this->em->persist($transaction);
    $this->em->flush();    
  }
  
  public function adjustStock(int $stockTargetId, float $newQty, ?string $reason = null): void
  {
    $target = $this->requireTarget($stockTargetId);
    
    $oldQty = $target->getCurrentQty();
    $delta = $newQty - $oldQty;
    
    if ($delta === 0.0) {
      return;
    }
    
    $transaction = new StockTransaction();
    $transaction->setStockTarget($target);
    $transaction->setQty($delta);
    $transaction->setReason($reason);
    $transaction->setUseType('adjustment');
    
    $target->setCurrentQty($newQty);
    
    $this->em->persist($transaction);
    $this->em->flush();
  }

  public function consumeStock(int $stockTargetId, float $quantity, ?string $reason = null): void
  {
    $target = $this->requireTarget($stockTargetId);
    
    $newQty = $target->getCurrentQty() - $quantity;
    
    if ($newQty < 0) {
      throw new \RuntimeException("Insufficient stock for target $stockTargetId");
    }

    $transaction = new StockTransaction();
    $transaction->setStockTarget($target);
    $transaction->setQty(-$quantity);
    $transaction->setReason($reason);
    $transaction->setUseType('consumption');
    
    $target->setCurrentQty($newQty);
    
    $this->em->persist($transaction);
    $this->em->flush();    
    }

  private function requireTarget(int $id): StockTarget
  {
    $target = $this->targetRepo->find($id);
    
    if (!$target) {
      throw new \InvalidArgumentException("Stock target not found for: $id");
    }
    return $target;
  }

  public function bulkCheckStock(array $itemQuantities): ServiceResponse
  {
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

     if ($isShort) {
       return ServiceResponse::failure(
         errors: 'Insufficient stock',
         data: $results,
       );
     }
     else {
       return ServiceResponse::success(
         data: $results,
         message: 'All stock available',
       );
     }
  }

  public function recordBulkStockCount(array $countedQuantities, ?string $notes = ''): void
  {
    $now = new \DateTime();

    $count = new StockCount();
    $count->setTimestamp($now);
    $count->setNotes($notes ?? '');
    
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
      $this->em->persist($item);

      $txn = new StockTransaction();
      $txn->setStockTarget($target);
      $txn->setQty($countedQty);
      $txn->setUseType('manual_count');
      $txn->setUnit($target->getBaseUnit());
      $txn->setReason('Manual stock count from bulk entry at ' . $now->format('Y-m-d H:i'));
      
      $this->em->persist($txn);

      // TODO: consider whether to defer setCurrentQty to Estimator service
      $target->setCurrentQty($countedQty);
    }
    
    $this->em->persist($count);
    $this->em->flush();
  }
}

?>
