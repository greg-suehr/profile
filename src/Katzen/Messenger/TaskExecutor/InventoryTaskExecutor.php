<?php

namespace App\Katzen\Messenger\TaskExecutor;

use App\Katzen\Service\InventoryService;

final class InventoryTaskExecutor implements AsyncTaskExecutorInterface
{
    public function __construct(
      private InventoryService $inventoryService,
    ) {}

    public function supports(string $taskType): bool
    {
        return in_array($taskType, ['consume_stock'], true);
    }

    public function execute(string $taskType, array $payload, array $options = []): void
    {
        match ($taskType) {
            'consume_stock' => $this->inventoryService->consumeStock(
                $payload['stock_target.id'],
                $payload['quantity'],
            ),
        };
    }
}

?>
