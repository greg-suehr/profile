<?php

namespace App\Katzen\Messenger\TaskExecutor;

interface AsyncTaskExecutorInterface
{
    public function supports(string $taskType): bool;

    public function execute(string $taskType, array $payload, array $options = []): void;
}

?>
