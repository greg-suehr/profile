<?php

namespace App\Katzen\Dashboard\Widget;

use App\Katzen\Messenger\QueueMonitor;

final class MessengerHealthWidget implements WidgetInterface
{
  public function __construct(private QueueMonitor $qm) {}

  public function toArray(): array
  {
    $rows = $this->qm->snapshot();
    $failed = $this->qm->failedCount();
    $lag = $this->qm->oldestReadyLagSec('async'); // null if empty

    dd($rows);
    
    return [
      'id' => 'messenger-health',
      'title' => 'Queue Health',
      'type' => 'stat-table',
      'data' => [
        'queues' => $rows,
        'failed' => $failed,
        'oldest_ready_lag_sec' => $lag,
      ],
      'badges' => [
        $failed > 0 ? ['text' => 'Failed', 'variant' => 'danger'] : ['text' => 'OK', 'variant' => 'success']
      ],
    ];
  }

  public function getKey(): string { return 'system.messenger.health'; }
  
  public function getViewModel(): WidgetView
  {
    $r = $this->toArray();

    $subtitle = sprintf('%d pending | %d errors', $r['data']['queues'], $r['data']['failed']);
    $tone = $r['data']['failed'] > 0 ? 'error' : ($r['data']['queues'] > 10 ? 'warning' : 'success');
    
    return new WidgetView(
      key: $this->getKey(),
      title: 'Background Tasks',
      value: (string)$r['data']['queues'],
      subtitle: $subtitle,
      tone: $tone,
    );
  }
}
