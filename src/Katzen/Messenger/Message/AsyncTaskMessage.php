<?php
namespace App\Katzen\Messenger\Message;

final class AsyncTaskMessage
{
    public function __construct(
        public readonly string $taskType,
        public readonly array $payload = [],
        public readonly array $options = [],
    ) {}
}
?>
