<?php

namespace App\Katzen\Messenger\TaskExecutor;

use App\Katzen\Service\InventoryServiceInterface;

final class InventoryTaskExecutor implements AsyncTaskExecutorInterface
{
    public function __construct(
    ) {}

    public function supports(string $taskType): bool
    {
        return in_array($taskType, ['consume_stock'], true);
    }

    public function execute(string $taskType, array $payload, array $options = []): void
    {
        match ($taskType) {
            'consume_stock' => $this->inventoryService->consumeStock(
                $payload['item_id'],
                $payload['quantity'],
            ),
        };
    }
}

?>
