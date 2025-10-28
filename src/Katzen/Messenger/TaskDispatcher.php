<?php

namespace App\Katzen\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class TaskDispatcher
{
  public function __construct(private MessageBusInterface $bus) {}

  /**
   * Runs tasks inline on dev, or queue them to an async transport in prod
   *
   * @param $message
   * @param $transport: optional, null uses routing rules, force a transport for testing
   * @param $delayMs: wait this many miliseconds before running the task
   */
  public function send(object $message, ?string $transport = null, ?int $delayMs = null): Envelope
  {
    $stamps = [];

    if ($transport) {
      $stamps[] = new TransportNamesStamp([$transport]);
    }
    if ($delayMs !== null) {
      $stamps[] = new DelayStamp($delayMs);
    }
    
    return $this->bus->dispatch(new Envelope($message, $stamps));
  }
}
