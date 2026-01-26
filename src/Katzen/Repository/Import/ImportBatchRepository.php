<?php

namespace App\Katzen\Repository\Import;

use App\Katzen\Entity\Import\ImportBatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ImportBatch entities.
 * 
 * Handles storage and retrieval of import batch records,
 * including progress tracking, status filtering, and statistics.
 * 
 * @extends ServiceEntityRepository<ImportBatch>
 */
class ImportBatchRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ImportBatch::class);
  }

  public function save(ImportBatch $batch, bool $flush = true): void
  {
    $this->getEntityManager()->persist($batch);
        
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(ImportBatch $batch, bool $flush = true): void
  {
    $this->getEntityManager()->remove($batch);
        
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find batches by status.
   * 
   * @param string|string[] $status Single status or array of statuses
   * @return ImportBatch[]
   */
  public function findByStatus(string|array $status): array
  {
    $statuses = (array) $status;
    
    return $this->createQueryBuilder('b')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('b.started_at', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find all pending batches (not yet started).
   * 
   * @return ImportBatch[]
   */
  public function findPending(): array
  {
    return $this->findByStatus(ImportBatch::STATUS_PENDING);
  }

  /**
   * Find all currently processing batches.
   * 
   * @return ImportBatch[]
   */
  public function findProcessing(): array
  {
    return $this->findByStatus(ImportBatch::STATUS_PROCESSING);
  }

  /**
   * Find all completed batches.
   * 
   * @return ImportBatch[]
   */
  public function findCompleted(): array
  {
    return $this->findByStatus(ImportBatch::STATUS_COMPLETED);
  }

  /**
   * Find all failed batches.
   * 
   * @return ImportBatch[]
   */
  public function findFailed(): array
  {
    return $this->findByStatus(ImportBatch::STATUS_FAILED);
  }

  /**
   * Find batches that can be rolled back (completed or failed).
   * 
   * @return ImportBatch[]
   */
  public function findRollbackable(): array
  {
    return $this->findByStatus([
      ImportBatch::STATUS_COMPLETED,
      ImportBatch::STATUS_FAILED,
    ]);
  }
  
  /**
   * Find active batches (pending or processing).
   * 
   * @return ImportBatch[]
   */
  public function findActive(): array
  {
      return $this->findByStatus([
        ImportBatch::STATUS_PENDING,
        ImportBatch::STATUS_PROCESSING,
    ]);
  }

  /**
   * Find batches created by a specific user.
   * 
   * @return ImportBatch[]
   */
  public function findByUser(int $userId, ?string $status = null): array
  {
    $qb = $this->createQueryBuilder('b')
            ->andWhere('b.created_by = :userId')
            ->setParameter('userId', $userId);
        
    if ($status !== null) {
      $qb->andWhere('b.status = :status')
         ->setParameter('status', $status);
    }
    
    return $qb->orderBy('b.started_at', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find batches within a date range.
   * 
   * @return ImportBatch[]
   */
  public function findByDateRange(
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array {
    return $this->createQueryBuilder('b')
            ->andWhere('b.started_at >= :from')
            ->andWhere('b.started_at <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.started_at', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find batches from today.
   * 
   * @return ImportBatch[]
   */
  public function findToday(): array
  {
    $today = new \DateTime('today');
    $tomorrow = new \DateTime('tomorrow');
    
    return $this->findByDateRange($today, $tomorrow);
  }
  
  /**
   * Find recent batches.
   * 
   * @return ImportBatch[]
   */
  public function findRecent(int $limit = 20): array
  {
    return $this->createQueryBuilder('b')
            ->orderBy('b.started_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Find batches by mapping ID.
   * 
   * @return ImportBatch[]
   */
  public function findByMapping(int $mappingId): array
  {
    return $this->createQueryBuilder('b')
            ->andWhere('b.mapping = :mappingId')
            ->setParameter('mappingId', $mappingId)
            ->orderBy('b.started_at', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Count batches by status.
   * 
   * @return array<string, int> ['pending' => 2, 'completed' => 15, ...]
   */
  public function countByStatus(): array
  {
    $results = $this->createQueryBuilder('b')
            ->select('b.status, COUNT(b.id) as count')
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();
    
    $counts = [];
    foreach ($results as $row) {
      $counts[$row['status']] = (int) $row['count'];
    }
    
    return $counts;
  }
  
  /**
   * Get summary statistics for all batches.
   * 
   * @return array{total_batches: int, total_rows: int, successful_rows: int, failed_rows: int}
   */
  public function getStatistics(): array
  {
    $result = $this->createQueryBuilder('b')
            ->select('
                COUNT(b.id) as total_batches,
                COALESCE(SUM(b.total_rows), 0) as total_rows,
                COALESCE(SUM(b.successful_rows), 0) as successful_rows,
                COALESCE(SUM(b.failed_rows), 0) as failed_rows
            ')
            ->getQuery()
            ->getSingleResult();
        
    return [
      'total_batches' => (int) $result['total_batches'],
      'total_rows' => (int) $result['total_rows'],
      'successful_rows' => (int) $result['successful_rows'],
      'failed_rows' => (int) $result['failed_rows'],
    ];
  }

  /**
   * Get statistics for a specific date range.
   * 
   * @return array{total_batches: int, total_rows: int, successful_rows: int, failed_rows: int, success_rate: float}
   */
  public function getStatisticsForPeriod(
    \DateTimeInterface $from,
    \DateTimeInterface $to
  ): array {
    $result = $this->createQueryBuilder('b')
            ->select('
                COUNT(b.id) as total_batches,
                COALESCE(SUM(b.total_rows), 0) as total_rows,
                COALESCE(SUM(b.successful_rows), 0) as successful_rows,
                COALESCE(SUM(b.failed_rows), 0) as failed_rows
            ')
            ->andWhere('b.started_at >= :from')
            ->andWhere('b.started_at <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();
    
    $totalRows = (int) $result['total_rows'];
    $successfulRows = (int) $result['successful_rows'];
    
    return [
      'total_batches' => (int) $result['total_batches'],
      'total_rows' => $totalRows,
      'successful_rows' => $successfulRows,
      'failed_rows' => (int) $result['failed_rows'],
      'success_rate' => $totalRows > 0 
        ? round(($successfulRows / $totalRows) * 100, 2) 
        : 0.0,
    ];
  }
  
  /**
   * Get daily import counts for charting.
   * 
   * @return array<string, array{batches: int, rows: int, successful: int}>
   */
  public function getDailyStats(int $days = 30): array
  {
    $from = new \DateTime("-{$days} days");
        
    $results = $this->createQueryBuilder('b')
            ->select("
                DATE(b.started_at) as date,
                COUNT(b.id) as batches,
                COALESCE(SUM(b.total_rows), 0) as rows,
                COALESCE(SUM(b.successful_rows), 0) as successful
            ")
            ->andWhere('b.started_at >= :from')
            ->setParameter('from', $from)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
        
    $stats = [];
    foreach ($results as $row) {
      $stats[$row['date']] = [
        'batches' => (int) $row['batches'],
        'rows' => (int) $row['rows'],
        'successful' => (int) $row['successful'],
      ];
    }
    
    return $stats;
  }

  /**
   * Find batches stuck in processing state.
   * Useful for detecting abandoned or crashed imports.
   * 
   * @param int $minutesThreshold Consider stuck if processing longer than this
   * @return ImportBatch[]
   */
  public function findStuckBatches(int $minutesThreshold = 60): array
  {
    $threshold = new \DateTime("-{$minutesThreshold} minutes");
        
    return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->andWhere('b.updated_at < :threshold')
            ->setParameter('status', ImportBatch::STATUS_PROCESSING)
            ->setParameter('threshold', $threshold)
            ->orderBy('b.started_at', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find old completed batches that might be candidates for cleanup.
   * 
   * @param int $daysOld Batches older than this many days
   * @return ImportBatch[]
   */
  public function findOldBatches(int $daysOld = 90): array
  {
    $threshold = new \DateTime("-{$daysOld} days");
        
    return $this->createQueryBuilder('b')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('b.completed_at < :threshold')
            ->setParameter('statuses', [
              ImportBatch::STATUS_COMPLETED,
              ImportBatch::STATUS_ROLLED_BACK,
            ])
            ->setParameter('threshold', $threshold)
            ->orderBy('b.completed_at', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Update batch progress counters efficiently.
   * Uses direct UPDATE to avoid loading the full entity.
   */
  public function updateProgress(
    int $batchId,
    int $processedRows,
    int $successfulRows,
    int $failedRows
  ): void {
    $this->createQueryBuilder('b')
            ->update()
            ->set('b.processed_rows', ':processed')
            ->set('b.successful_rows', ':successful')
            ->set('b.failed_rows', ':failed')
            ->set('b.updated_at', ':now')
            ->where('b.id = :id')
            ->setParameter('processed', $processedRows)
            ->setParameter('successful', $successfulRows)
            ->setParameter('failed', $failedRows)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $batchId)
            ->getQuery()
            ->execute();
    }
  
  /**
   * Mark a batch as completed.
   */
  public function markCompleted(int $batchId): void
  {
    $this->createQueryBuilder('b')
            ->update()
            ->set('b.status', ':status')
            ->set('b.completed_at', ':now')
            ->set('b.updated_at', ':now')
            ->where('b.id = :id')
            ->setParameter('status', ImportBatch::STATUS_COMPLETED)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $batchId)
            ->getQuery()
            ->execute();
  }

  /**
   * Mark a batch as failed.
   */
  public function markFailed(int $batchId): void
  {
    $this->createQueryBuilder('b')
            ->update()
            ->set('b.status', ':status')
            ->set('b.completed_at', ':now')
            ->set('b.updated_at', ':now')
            ->where('b.id = :id')
            ->setParameter('status', ImportBatch::STATUS_FAILED)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $batchId)
            ->getQuery()
            ->execute();
  }
}
