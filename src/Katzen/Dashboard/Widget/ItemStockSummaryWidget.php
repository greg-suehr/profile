<?php

namespace App\Katzen\Dashboard\Widget;

use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\StockTargetRepository;

final class ItemStockSummaryWidget implements WidgetInterface
{
    public function __construct(
        private ItemRepository $items,
        private StockTargetRepository $targets
    ) {}

    public function getKey(): string { return 'kpi.items.stock'; }

    public function getViewModel(): WidgetView
    {
        $totalItems = $this->items->count([]);
        $low = $this->targets->countByStatus(['Low']);
        $out = $this->targets->countByStatus(['Out']);

        $subtitle = sprintf('%d low / %d out', $low, $out);
        $tone = $out > 0 ? 'error' : ($low > 0 ? 'warning' : 'success');

        return new WidgetView(
            key: $this->getKey(),
            title: 'Items',
            value: (string)$totalItems,
            subtitle: $subtitle,
            tone: $tone,
        );
    }
}

?>
