<?php

namespace App\Katzen\Service;

use App\Katzen\Entity\StockCount;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
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

  public function consumeStock(int $stockTargetId, float $quantity): void
  {
    $target = $this->requireTarget($stockTargetId);
    
    $newQty = $target->getCurrentQty() - $quantity;
    
    if ($newQty < 0) {
      throw new \RuntimeException("Insufficient stock for target $stockTargetId");
    }

    $transaction = new StockTransaction();
    $transaction->setStockTarget($target);
    $transaction->setQty(-$qty);
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
      throw new \InvalidArgumentException("Stock target not found for $type: $id");
    }
    return $target;
  }
}

?>
