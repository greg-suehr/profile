<?php

namespace App\Katzen\Repository\Import;

use App\Katzen\Entity\Import\ImportMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ImportMapping entities.
 * 
 * Handles storage and retrieval of field mapping configurations
 * used to translate CSV columns to Katzen entity fields.
 * 
 * @extends ServiceEntityRepository<ImportMapping>
 */
class ImportMappingRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ImportMapping::class);
  }

  public function save(ImportMapping $mapping, bool $flush = true): void
  {
    $this->getEntityManager()->persist($mapping);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(ImportMapping $mapping, bool $flush = true): void
  {
    $this->getEntityManager()->remove($mapping);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  /**
   * Find all active mappings for a specific entity type.
   * 
   * @return ImportMapping[]
   */
  public function findByEntityType(string $entityType): array
  {
    return $this->createQueryBuilder('m')
            ->andWhere('m.entity_type = :type')
            ->andWhere('m.is_active = :active')
            ->setParameter('type', $entityType)
            ->setParameter('active', true)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find all system templates (pre-configured mappings).
   * 
   * @return ImportMapping[]
   */
  public function findSystemTemplates(): array
  {
    return $this->createQueryBuilder('m')
            ->andWhere('m.is_system_template = :isSystem')
            ->andWhere('m.is_active = :active')
            ->setParameter('isSystem', true)
            ->setParameter('active', true)
            ->orderBy('m.entity_type', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /**
   * Find all user-created mappings.
   * 
   * @return ImportMapping[]
   */
  public function findUserMappings(?int $userId = null): array
  {
    $qb = $this->createQueryBuilder('m')
            ->andWhere('m.is_system_template = :isSystem')
            ->andWhere('m.is_active = :active')
            ->setParameter('isSystem', false)
            ->setParameter('active', true);
        
    if ($userId !== null) {
      $qb->andWhere('m.created_by = :userId')
               ->setParameter('userId', $userId);
    }
    
    return $qb->orderBy('m.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
  }
  
  /**
   * Find mappings by name (partial match).
   * 
   * @return ImportMapping[]
   */
  public function searchByName(string $name): array
  {
    return $this->createQueryBuilder('m')
            ->where('LOWER(m.name) LIKE LOWER(:name)')
            ->andWhere('m.is_active = :active')
            ->setParameter('name', '%' . $name . '%')
            ->setParameter('active', true)
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
  }

  /**
   * Find a mapping that matches specific field mappings exactly.
   * Useful for detecting duplicate mappings.
   */
  public function findByFieldMappings(string $entityType, array $fieldMappings): ?ImportMapping
  {
    ksort($fieldMappings);
    $normalizedJson = json_encode($fieldMappings);
    
    $mappings = $this->findByEntityType($entityType);
    
    foreach ($mappings as $mapping) {
      $existingMappings = $mapping->getFieldMappings();
      ksort($existingMappings);
      
      if (json_encode($existingMappings) === $normalizedJson) {
        return $mapping;
            }
    }
    
    return null;
  }

  /**
   * Count mappings by entity type.
   * 
   * @return array<string, int> ['order' => 5, 'item' => 3, ...]
   */
  public function countByEntityType(): array
  {
    $results = $this->createQueryBuilder('m')
            ->select('m.entity_type, COUNT(m.id) as count')
            ->andWhere('m.is_active = :active')
            ->setParameter('active', true)
            ->groupBy('m.entity_type')
            ->getQuery()
            ->getResult();
        
    $counts = [];
    foreach ($results as $row) {
      $counts[$row['entity_type']] = (int) $row['count'];
    }
    
    return $counts;
  }

  /**
   * Get all distinct entity types that have mappings.
   * 
   * @return string[]
   */
  public function findDistinctEntityTypes(): array
  {
    $results = $this->createQueryBuilder('m')
            ->select('DISTINCT m.entity_type')
            ->andWhere('m.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('m.entity_type', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        
    return $results;
  }

  /**
   * Find a system template by entity type and name.
   */
  public function findSystemTemplate(string $entityType, string $name): ?ImportMapping
  {
    return $this->createQueryBuilder('m')
            ->andWhere('m.entity_type = :type')
            ->andWhere('m.name = :name')
            ->andWhere('m.is_system_template = :isSystem')
            ->setParameter('type', $entityType)
            ->setParameter('name', $name)
            ->setParameter('isSystem', true)
            ->getQuery()
            ->getOneOrNullResult();
  }

  /**
   * Deactivate a mapping (soft delete).
   */
  public function deactivate(ImportMapping $mapping): void
  {
    $mapping->setIsActive(false);
    $this->save($mapping);
  }

  /**
   * Clone a mapping to create a user copy from a system template.
   */
  public function cloneMapping(ImportMapping $source, string $newName, ?int $userId = null): ImportMapping
  {
    $clone = new ImportMapping();
    $clone->setName($newName);
    $clone->setDescription($source->getDescription());
    $clone->setEntityType($source->getEntityType());
    $clone->setFieldMappings($source->getFieldMappings());
    $clone->setTransformationRules($source->getTransformationRules());
    $clone->setValidationRules($source->getValidationRules());
    $clone->setDefaultValues($source->getDefaultValues());
    $clone->setIsSystemTemplate(false);
    $clone->setCreatedBy($userId);
    
    $this->save($clone);
    
    return $clone;
  }

  /**
   * Find recently updated mappings.
   * 
   * @return ImportMapping[]
   */
  public function findRecent(int $limit = 10): array
  {
     return $this->createQueryBuilder('m')
            ->andWhere('m.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('m.updated_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
  }

  /**
   * Find mappings created by a specific user.
   * 
   * @return ImportMapping[]
   */
  public function findByCreator(int $userId): array
  {
    return $this->createQueryBuilder('m')
            ->andWhere('m.created_by = :userId')
            ->andWhere('m.is_active = :active')
            ->setParameter('userId', $userId)
            ->setParameter('active', true)
            ->orderBy('m.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
  }
}
