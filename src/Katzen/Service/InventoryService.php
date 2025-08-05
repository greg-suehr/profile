<?php

namespace App\Katzen\Service;

use App\Katzen\Repository\StockTargetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryService implements InventoryServiceInterface
{
    public function __construct(
        private StockTargetRepository $repo,
        private EntityManagerInterface $em,
    ) {}

    public function consumeStock(int $targetId, float $quantity): void
    {
        $target = $this->repo->find($targetId);

        if (!$target) {
            throw new \InvalidArgumentException("Stock target not found: $targetId");
        }

        $newQty = $target->getAvailableQty() - $quantity;

        if ($newQty < 0) {
            throw new \RuntimeException("Insufficient stock for target $targetId");
        }

        $target->setAvailableQty($newQty);
        $this->em->flush();
    }
}

?>
