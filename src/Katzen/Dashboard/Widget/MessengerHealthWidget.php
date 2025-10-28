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
    $queueStats = $this->toArray();

    $ready = 0;
    $delayed = 0;
    $in_progress = 0;
    $failed = $queueStats['data']['failed'];

    foreach ($queueStats['data']['queues'] as $q)
    {
      $ready += $q['ready'];
      $delayed += $q['delayed'];
      $in_progress += $q['in_progress'];
    }

    $total = $ready + $delayed + $in_progress;
    
    $subtitle = sprintf('%d pending | %d running | %d errors', $ready, $in_progress, $failed);
    $tone = $failed > 0 ? 'error' : ($delayed > 0 ? 'warning' : ($ready > 25 ? 'warning' : 'success'));
    
    return new WidgetView(
      key: $this->getKey(),
      title: 'Background Tasks',
      value: (string)$total,
      subtitle: $subtitle,
      tone: $tone,
    );
  }
}
