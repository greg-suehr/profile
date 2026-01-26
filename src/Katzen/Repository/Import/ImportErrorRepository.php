<?php

namespace App\Katzen\Repository\Import;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportError;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ImportError entities.
 * 
 * Handles storage and retrieval of import errors,
 * including error analysis, grouping, and reporting.
 * 
 * @extends ServiceEntityRepository<ImportError>
 */
class ImportErrorRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ImportError::class);
  }

  public function save(ImportError $error, bool $flush = true): void
  {
    $this->getEntityManager()->persist($error);
        
    if ($flush) {
      $this->getEntityManager()->flush();
      }
  }

  /**
   * Save multiple errors in a single flush.
   * 
   * @param ImportError[] $errors
   */
  public function saveAll(array $errors): void
  {
    $em = $this->getEntityManager();
        
    foreach ($errors as $error) {
      $em->persist($error);
    }
        
    $em->flush();
  }

  public function saveBatch(array $errors, int $batchSize = 100): void
  {
    $em = $this->getEntityManager();
    $count = 0;
    
    foreach ($errors as $error) {
        $em->persist($error);
        
        if (++$count % $batchSize === 0) {
          $em->flush();
          $em->clear();
        }
    }
    
    $em->flush();
    $em->clear();
  }

  public function remove(ImportError $error, bool $flush = true): void
  {
    $this->getEntityManager()->remove($error);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find all errors for a batch.
   * 
   * @return ImportError[]
   */
  public function findByBatch(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->orderBy('e.row_number', 'ASC')
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find errors for a batch, paginated.
   * 
   * @return ImportError[]
   */
  public function findByBatchPaginated(
    ImportBatch|int $batch,
    int $page = 1,
    int $limit = 50
  ): array {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
    $offset = ($page - 1) * $limit;
    
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->orderBy('e.row_number', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Count errors for a batch.
   */
  public function countByBatch(ImportBatch|int $batch): int
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->getQuery()
            ->getSingleScalarResult();
  }

  /**
   * Delete all errors for a batch.
   * Used when rolling back or re-running an import.
   */
  public function deleteByBatch(ImportBatch|int $batch): int
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->getQuery()
            ->execute();
  }

  /**
   * Find errors by type within a batch.
   * 
   * @return ImportError[]
   */
  public function findByType(ImportBatch|int $batch, string $errorType): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->andWhere('e.error_type = :type')
            ->setParameter('batchId', $batchId)
            ->setParameter('type', $errorType)
            ->orderBy('e.row_number', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find errors by severity within a batch.
   * 
   * @return ImportError[]
   */
  public function findBySeverity(ImportBatch|int $batch, string $severity): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->andWhere('e.severity = :severity')
            ->setParameter('batchId', $batchId)
            ->setParameter('severity', $severity)
            ->orderBy('e.row_number', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find critical errors that blocked the import.
   * 
   * @return ImportError[]
   */
  public function findCritical(ImportBatch|int $batch): array
  {
    return $this->findBySeverity($batch, ImportError::SEVERITY_CRITICAL);
  }

  /**
   * Find errors affecting a specific field.
   * 
   * @return ImportError[]
   */
  public function findByField(ImportBatch|int $batch, string $fieldName): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->andWhere('e.field_name = :field')
            ->setParameter('batchId', $batchId)
            ->setParameter('field', $fieldName)
            ->orderBy('e.row_number', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get error summary grouped by type for a batch.
   * 
   * @return array<string, int> ['validation' => 15, 'transformation' => 3, ...]
   */
  public function getErrorSummaryByType(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    $results = $this->createQueryBuilder('e')
            ->select('e.error_type, COUNT(e.id) as count')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->groupBy('e.error_type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
        
    $summary = [];
    foreach ($results as $row) {
      $summary[$row['error_type']] = (int) $row['count'];
    }
        
    return $summary;
  }

  /**
   * Get error summary grouped by field for a batch.
   * 
   * @return array<string, int> ['unit_price' => 10, 'order_date' => 5, ...]
   */
  public function getErrorSummaryByField(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    $results = $this->createQueryBuilder('e')
            ->select('COALESCE(e.field_name, \'general\') as field, COUNT(e.id) as count')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->groupBy('field')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
        
    $summary = [];
    foreach ($results as $row) {
      $summary[$row['field']] = (int) $row['count'];
    }
    
    return $summary;
  }

  /**
   * Get error summary grouped by severity for a batch.
   * 
   * @return array<string, int> ['error' => 20, 'warning' => 5, 'critical' => 1]
   */
  public function getErrorSummaryBySeverity(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    $results = $this->createQueryBuilder('e')
            ->select('e.severity, COUNT(e.id) as count')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->groupBy('e.severity')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
        
    $summary = [];
    foreach ($results as $row) {
      $summary[$row['severity']] = (int) $row['count'];
    }
    
    return $summary;
  }
  
  /**
   * Get comprehensive error summary for a batch.
   * 
   * @return array{
   *   total: int,
   *   by_type: array<string, int>,
   *   by_field: array<string, int>,
   *   by_severity: array<string, int>,
   *   sample_errors: ImportError[]
   * }
   */
  public function getComprehensiveSummary(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return [
      'total' => $this->countByBatch($batchId),
      'by_type' => $this->getErrorSummaryByType($batchId),
      'by_field' => $this->getErrorSummaryByField($batchId),
      'by_severity' => $this->getErrorSummaryBySeverity($batchId),
      'sample_errors' => $this->findByBatchPaginated($batchId, 1, 10),
    ];
  }

  /**
   * Get unique error messages for a batch (deduplicated).
   * Useful for identifying patterns in errors.
   * 
   * @return array<array{message: string, count: int, first_row: int}>
   */
  public function getUniqueErrorMessages(ImportBatch|int $batch, int $limit = 20): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    $results = $this->createQueryBuilder('e')
            ->select('e.error_message, COUNT(e.id) as count, MIN(e.row_number) as first_row')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->groupBy('e.error_message')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
    return array_map(fn($row) => [
      'message' => $row['error_message'],
      'count' => (int) $row['count'],
      'first_row' => (int) $row['first_row'],
    ], $results);
  }

  /**
   * Get all errors for a specific row.
   * 
   * @return ImportError[]
   */
  public function findByRow(ImportBatch|int $batch, int $rowNumber): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->andWhere('e.batch = :batchId')
            ->andWhere('e.row_number = :row')
            ->setParameter('batchId', $batchId)
            ->setParameter('row', $rowNumber)
            ->orderBy('e.severity', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get row numbers that have errors.
   * 
   * @return int[]
   */
  public function getErrorRowNumbers(ImportBatch|int $batch): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    return $this->createQueryBuilder('e')
            ->select('DISTINCT e.row_number')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->orderBy('e.row_number', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
  }

  /**
   * Get rows with the most errors (problematic rows).
   * 
   * @return array<array{row_number: int, error_count: int}>
   */
  public function getProblematicRows(ImportBatch|int $batch, int $limit = 10): array
  {
    $batchId = $batch instanceof ImportBatch ? $batch->getId() : $batch;
        
    $results = $this->createQueryBuilder('e')
            ->select('e.row_number, COUNT(e.id) as error_count')
            ->andWhere('e.batch = :batchId')
            ->setParameter('batchId', $batchId)
            ->groupBy('e.row_number')
            ->orderBy('error_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
    return array_map(fn($row) => [
      'row_number' => (int) $row['row_number'],
      'error_count' => (int) $row['error_count'],
    ], $results);
  }

  /**
   * Get errors formatted for CSV export.
   * 
   * @return array<array{row: int, type: string, severity: string, field: ?string, message: string, suggested_fix: ?string}>
   */
  public function getErrorsForExport(ImportBatch|int $batch): array
  {
    $errors = $this->findByBatch($batch);
        
    return array_map(fn(ImportError $e) => [
      'row' => $e->getRowNumber(),
      'type' => $e->getErrorType(),
      'severity' => $e->getSeverity(),
      'field' => $e->getFieldName(),
      'message' => $e->getErrorMessage(),
      'suggested_fix' => $e->getSuggestedFix(),
    ], $errors);
  }
}
