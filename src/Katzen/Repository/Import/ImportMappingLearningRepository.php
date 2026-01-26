<?php

namespace App\Katzen\Repository\Import;

use App\Katzen\Entity\Import\ImportMappingLearning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ImportMappingLearning entities.
 * 
 * Handles storage and retrieval of learned column-to-field mappings.
 * This provides the "memory" for the intelligent mapping system,
 * learning from user corrections to improve future suggestions.
 * 
 * @extends ServiceEntityRepository<ImportMappingLearning>
 */
class ImportMappingLearningRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ImportMappingLearning::class);
  }

  public function save(ImportMappingLearning $learning, bool $flush = true): void
  {
    $this->getEntityManager()->persist($learning);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(ImportMappingLearning $learning, bool $flush = true): void
  {
    $this->getEntityManager()->remove($learning);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find a learned mapping by column name and entity type.
   * This is the primary lookup used during mapping detection.
   * 
   * Returns the most successful mapping if multiple exist.
   */
  public function findByColumnName(string $columnName, string $entityType): ?ImportMappingLearning
  {
    $normalized = $this->normalizeColumnName($columnName);
    
    return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.column_name) = :column')
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('column', $normalized)
            ->setParameter('entityType', $entityType)
            ->orderBy('l.success_count', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
  }

  /**
   * Helpful helper to generate fingerprints for findByFingerprint
   *
   * Don't use it for other things!
   */  
  public static function generateFingerprint(array $headers): string
  {
    sort($headers);
    return md5(implode('|', array_map('strtolower', $headers)));
  }

  /**
   * Find a learned mapping by header fingerprint.
   * Used for full-file pattern matching (when we've seen this exact CSV structure before).
   */
  public function findByFingerprint(string $fingerprint, string $entityType): ?ImportMappingLearning
  {
    return $this->createQueryBuilder('l')
            ->andWhere('l.header_fingerprint = :fingerprint')
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('fingerprint', $fingerprint)
            ->setParameter('entityType', $entityType)
            ->orderBy('l.success_count', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
  }
  
  /**
   * Find or create a learning record.
   *
   * Even if we create it, you are responsible for flushing it!
   * (Just like you would be if we found it and you updated it...)
   */
  public function findOrCreate(
    string $columnName,
    string $targetField,
    string $entityType
  ): ImportMappingLearning {
    $normalized = $this->normalizeColumnName($columnName);
        
    $existing = $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.column_name) = :column')
            ->andWhere('l.target_field = :field')
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('column', $normalized)
            ->setParameter('field', $targetField)
            ->setParameter('entityType', $entityType)
            ->getQuery()
            ->getOneOrNullResult();
        
    if ($existing) {
      return $existing;
    }
    
    $learning = new ImportMappingLearning();
    $learning->setColumnName($normalized);
    $learning->setTargetField($targetField);
    $learning->setEntityType($entityType);
    
    return $learning;
  }

  /**
   * Get all learned mappings for a column name across all entity types.
   * Useful for suggesting mappings when entity type is ambiguous.
   * 
   * @return ImportMappingLearning[]
   */
  public function findAllByColumnName(string $columnName): array
  {
    $normalized = $this->normalizeColumnName($columnName);
    
    return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.column_name) = :column')
            ->setParameter('column', $normalized)
            ->orderBy('l.success_count', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get the top N most confident mappings for a column.
   * 
   * @return ImportMappingLearning[]
   */
  public function findTopMappings(string $columnName, string $entityType, int $limit = 3): array
  {
    $normalized = $this->normalizeColumnName($columnName);
    
    return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.column_name) = :column')
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('column', $normalized)
            ->setParameter('entityType', $entityType)
            ->orderBy('l.success_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find similar column names using fuzzy matching.
   * Useful when exact match fails.
   * 
   * @return ImportMappingLearning[]
   */
  public function findSimilarColumnNames(string $columnName, string $entityType, int $limit = 5): array
  {
    $normalized = $this->normalizeColumnName($columnName);
        
    return $this->createQueryBuilder('l')
            ->andWhere('l.entity_type = :entityType')
            ->andWhere('LOWER(l.column_name) LIKE :pattern')
            ->setParameter('entityType', $entityType)
            ->setParameter('pattern', '%' . $normalized . '%')
            ->orderBy('l.success_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Record a successful mapping (user confirmed the suggestion was correct).
   */
  public function recordSuccess(string $columnName, string $targetField, string $entityType): void
  {
    $learning = $this->findOrCreate($columnName, $targetField, $entityType);
    $learning->incrementSuccessCount();
    $this->save($learning);
  }

  /**
   * Record a failed suggestion (user corrected the mapping).
   */
  public function recordCorrection(
    string $columnName,
    string $suggestedField,
    string $actualField,
    string $entityType
  ): void {
    $correct = $this->findOrCreate($columnName, $actualField, $entityType);
    $correct->incrementSuccessCount();
    $this->save($correct, false);
        
    if ($suggestedField !== $actualField) {
      $failed = $this->findOrCreate($columnName, $suggestedField, $entityType);
      $failed->recordFailedSuggestion($actualField);
      $this->save($failed, false);
    }
    
    $this->getEntityManager()->flush();
  }

  /**
   * Store the header fingerprint for full-file pattern matching.
   */
  public function recordFingerprint(
     string $fingerprint,
     string $columnName,
     string $targetField,
     string $entityType
  ): void {
    $learning = $this->findOrCreate($columnName, $targetField, $entityType);
    $learning->setHeaderFingerprint($fingerprint);
    $this->save($learning);
  }

  /**
   * Get all learned mappings for an entity type.
   * 
   * @return ImportMappingLearning[]
   */
  public function findByEntityType(string $entityType): array
  {
    return $this->createQueryBuilder('l')
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('l.success_count', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Get the most commonly mapped column names.
   * 
   * @return array<array{column_name: string, entity_type: string, target_field: string, success_count: int}>
   */
  public function getTopMappings(int $limit = 50): array
  {
    $results = $this->createQueryBuilder('l')
            ->select('l.column_name, l.entity_type, l.target_field, l.success_count')
            ->orderBy('l.success_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    
    return $results;
  }

  /**
   * Get mapping statistics by entity type.
   * 
   * @return array<string, array{total: int, unique_columns: int, avg_success: float}>
   */
  public function getStatisticsByEntityType(): array
  {
    $results = $this->createQueryBuilder('l')
            ->select('
                l.entity_type,
                COUNT(l.id) as total,
                COUNT(DISTINCT l.column_name) as unique_columns,
                AVG(l.success_count) as avg_success
            ')
            ->groupBy('l.entity_type')
            ->getQuery()
            ->getResult();
    
    $stats = [];
    foreach ($results as $row) {
      $stats[$row['entity_type']] = [
        'total' => (int) $row['total'],
        'unique_columns' => (int) $row['unique_columns'],
        'avg_success' => round((float) $row['avg_success'], 2),
      ];
    }
    
    return $stats;
  }

  /**
   * Get columns that have conflicting mappings (mapped to different fields).
   * Useful for identifying ambiguous column names.
   * 
   * @return array<array{column_name: string, entity_type: string, mappings: array}>
   */
  public function findConflictingMappings(): array
  {
    $conflicts = $this->createQueryBuilder('l')
            ->select('l.column_name, l.entity_type, COUNT(DISTINCT l.target_field) as field_count')
            ->groupBy('l.column_name, l.entity_type')
            ->having('field_count > 1')
            ->getQuery()
            ->getResult();
        
    $result = [];
    foreach ($conflicts as $conflict) {
      $mappings = $this->createQueryBuilder('l')
                ->select('l.target_field, l.success_count')
                ->andWhere('l.column_name = :column')
                ->andWhere('l.entity_type = :entityType')
                ->setParameter('column', $conflict['column_name'])
                ->setParameter('entityType', $conflict['entity_type'])
                ->orderBy('l.success_count', 'DESC')
                ->getQuery()
                ->getResult();
      
      $result[] = [
        'column_name' => $conflict['column_name'],
        'entity_type' => $conflict['entity_type'],
        'mappings' => $mappings,
      ];
    }
    
    return $result;
  }

  /**
   * Get mappings that have frequently failed suggestions.
   * Useful for identifying problematic auto-detection patterns.
   * 
   * @return ImportMappingLearning[]
   */
  public function findProblematicMappings(int $minFailures = 3): array
  {
      return $this->createQueryBuilder('l')
            ->andWhere('l.failed_suggestions IS NOT NULL')
            ->andWhere('JSON_LENGTH(l.failed_suggestions) >= :minFailures')
            ->setParameter('minFailures', $minFailures)
            ->orderBy('l.success_count', 'ASC')
            ->getQuery()
            ->getResult();
    }

  
  /**
   * Remove mappings with very low success counts (likely noise).
   * 
   * @return int Number of records deleted
   */
  public function pruneUnsuccessful(int $maxSuccessCount = 1, int $daysOld = 30): int
  {
    $threshold = new \DateTime("-{$daysOld} days");
        
    return $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.success_count <= :maxSuccess')
            ->andWhere('l.updated_at < :threshold')
            ->setParameter('maxSuccess', $maxSuccessCount)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
  }

  /**
   * Clear all learned mappings for an entity type.
   * 
   * @return int Number of records deleted
   */
  public function clearByEntityType(string $entityType): int
  {
    return $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.entity_type = :entityType')
            ->setParameter('entityType', $entityType)
            ->getQuery()
            ->execute();
  }

  
  /**
   * Normalize column name for consistent lookups.
   */
  private function normalizeColumnName(string $columnName): string
  {
    $normalized = strtolower($columnName);        
    $normalized = preg_replace('/[\s\-\.]+/', '_', $normalized);    
    $normalized = trim($normalized, '_');
        
    return $normalized;
  }
}
