<?php

namespace App\Katzen\Messenger\TaskExecutor;

use App\Katzen\Service\InventoryService;
use App\Katzen\Service\Response\ServiceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class InventoryTaskExecutor implements AsyncTaskExecutorInterface
{
  private const SUPPORTED = ['consume_stock'];
  
  public function __construct(
    private InventoryService $inventoryService,
    private LoggerInterface $logger,
  ) {}
  
  public function supports(string $taskType): bool
  {
    return in_array($taskType, ['consume_stock'], true);
  }
  
  /**
   * @param array{
   *   stock_target.id:int|string,
   *   quantity:float|int|string,
   *   reason?:string|null
   * } $payload
   */
  public function execute(string $taskType, array $payload, array $options = []): void
  {
    if (!isset($payload['stock_target.id'], $payload['quantity'])) {
      throw new UnrecoverableMessageHandlingException('Missing required keys: stock_target.id, quantity');
    }
    
    $targetId = (int) $payload['stock_target.id'];
    $quantity = (float) $payload['quantity'];
    $reason   = $payload['reason'] ?? null;

    # TODO: improve taskwise param validation
    if ($targetId <= 0) {
      throw new UnrecoverableMessageHandlingException('Invalid stock_target.id');
    }
    
    if ($taskType === 'consume_stock' && $quantity <= 0) {
      throw new UnrecoverableMessageHandlingException('Quantity must be positive for consume_stock');
    }
    
    $result = match ($taskType) {
      'consume_stock' => $this->inventoryService->consumeStock(
        $payload['stock_target.id'],
        $payload['quantity'],
      ),
    };

    # TODO: improve taskwise log format
    if ($result->isSuccess()) {
      $data = $result->getData();
      $this->logger->info('Inventory task succeeded', [
        'task'    => $taskType,
        'target'  => $targetId,
        'delta'   => $data['delta'] ?? null,
        'new_qty' => $data['new_qty'] ?? null,
      ]);
      return;
    }

    $exception = $this->classifyFailure($taskType, $payload, $result);
    
    // Log with context, then throw
    $this->logger->warning('Inventory task failed', [
      'task'     => $taskType,
      'payload'  => $payload,
      'message'  => $result->getMessage(),
      'errors'   => $result->getErrors(),
      'metadata' => $result->getMetadata(),
      'exception_class' => $exception::class,
    ]);

    throw $exception;
  }

  private function classifyFailure(string $taskType, array $payload, ServiceResponse $result): \Throwable
  {
    $errors = array_map('strval', (array) $result->getErrors());
    $message = (string) ($result->getMessage() ?? 'Task failed');

     $text = strtolower($message . ' ' . implode(' ', $errors));

     // TODO: improve taskwise and global param validation
     $isLogical =
       str_contains($text, 'quantity must be positive') ||
       str_contains($text, 'stock target not found') ||
       str_contains($text, 'invalid') ||
       str_contains($text, 'insufficient stock');
     
     if ($isLogical) {
       return new UnrecoverableMessageHandlingException($message);
     }

     return new RecoverableMessageHandlingException($message);
   }
}      
