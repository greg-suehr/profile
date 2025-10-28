<?php

namespace App\Katzen\Messenger;

use Doctrine\DBAL\Connection;

final class QueueMonitor
{
  public function __construct(private Connection $db) {}
  
  public function snapshot(): array
  {
        $sql = <<<SQL
SELECT
  queue_name,
  SUM(CASE WHEN delivered_at IS NULL AND available_at <= NOW() THEN 1 ELSE 0 END) AS ready,
  SUM(CASE WHEN delivered_at IS NULL AND available_at  > NOW() THEN 1 ELSE 0 END) AS delayed,
  SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END)                        AS in_progress
FROM messenger_messages
GROUP BY queue_name
ORDER BY queue_name
SQL;
        return $this->db->fetchAllAssociative($sql);
    }
  
  public function failedCount(): int
  {
    $sql = "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'";
    return (int) $this->db->fetchOne($sql);
  }

  public function oldestReadyLagSec(?string $queue = 'async'): ?int
  {
    $sql = "SELECT EXTRACT(EPOCH FROM (NOW() - MIN(available_at)))::int
                FROM messenger_messages
                WHERE queue_name = :q AND delivered_at IS NULL AND available_at <= NOW()";
    $val = $this->db->fetchOne($sql, ['q' => $queue]);
    return $val !== null ? (int)$val : null;
  }
}
