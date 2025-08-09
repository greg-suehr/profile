<?php

namespace App\Katzen\Service\Delete;

use App\Katzen\Entity\Item;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\StockTargetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ItemDeletionPolicy
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecipeRepository $recipes,
        private ItemRepository $items,
        private StockTargetRepository $targets
    ) {}

    /** Preflight: enumerate dependencies and say if deletion is OK under the given mode */
    public function preflight(Item $item, DeleteMode $mode): DeleteReport
    {
        $refs = $this->recipes->findIdsAndTitlesReferencingItem($item->getId());

        $target = $this->targets->findOneByItemId($item->getId());

        $facts = [
            'recipe_ref_count' => count($refs),
            'recipe_refs'      => array_slice($refs, 0, 10),
            'has_stock_target' => (bool)$target,
            'stock_target_id'  => $target?->getId(),
        ];

        $reasons = [];
        $ok = true;

        if ($mode === DeleteMode::BLOCK_IF_REFERENCED) {
            if ($facts['recipe_ref_count'] > 0) {
                $ok = false;
                $reasons[] = 'Item is used by active recipes.';
            }
        }

        return new DeleteReport($ok, $facts, $reasons);
    }

    /** Execute per mode. Keep today’s behavior strict; scaffold others. */
    public function execute(Item $item, DeleteMode $mode): void
    {
        $report = $this->preflight($item, $mode);
        if (!$report->ok) {
            throw new \DomainException('Deletion blocked: ' . implode(' ', $report->reasons));
        }

        match ($mode) {
            DeleteMode::BLOCK_IF_REFERENCED => $this->hardDeleteWithCleanup($item),
            DeleteMode::SOFT_DELETE => $this->softDelete($item),
            DeleteMode::FORCE_WITH_INVALIDATIONS => $this->forceWithInvalidations($item, $report),
            default => throw new \LogicException("Delete mode {$mode->value} not supported yet."),
        };
    }

    private function hardDeleteWithCleanup(Item $item): void
    {
        if ($t = $this->targets->findOneByItemId($item->getId())) {
            $this->em->remove($t);
        }
        $this->em->remove($item);
        $this->em->flush();
    }

    private function softDelete(Item $item): void
    {
        $item->setArchivedAt(new \DateTime());
        $this->em->flush();
    }

    private function forceWithInvalidations(Item $item, DeleteReport $report): void
    {
        // TODO: mark recipes and menus as 'needs_review', soft-delete item
        //       recipes.setNeedsReview("missing item #{$item->getId()}"), menus.archiveIfImpacted()
        $this->softDelete($item);
    }
}
?>
