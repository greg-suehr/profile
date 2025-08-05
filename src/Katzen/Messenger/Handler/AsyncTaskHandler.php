<?php

namespace App\Katzen\Messenger\Handler;

use App\Katzen\Messenger\Message\AsyncTaskMessage;
use App\Katzen\Messenger\TaskExecutor\AsyncTaskExecutorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AsyncTaskHandler
{
    public function __construct(
        private iterable $taskExecutors, // taskExecutors should be tagged services
    ) {}

    public function __invoke(AsyncTaskMessage $message): void
    {
        foreach ($this->taskExecutors as $executor) {
            if ($executor->supports($message->taskType)) {
                $executor->execute($message->taskType, $message->payload, $message->options);
                return;
            }
        }

        throw new \RuntimeException("No executor found for task: {$message->taskType}");
    }
}

?>
