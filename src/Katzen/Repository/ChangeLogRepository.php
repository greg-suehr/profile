<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\ChangeLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChangeLog>
 */
class ChangeLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeLog::class);
    }

    public function save(ChangeLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find changes for a specific entity type and ID with pagination
     */
    public function findEntityHistory(
        string $entityType,
        string $entityId,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->createQueryBuilder('c')
            ->where('c.entity_type = :type')
            ->andWhere('c.entity_id = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('c.changed_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent activity across all entities
     */
    public function getRecentActivity(int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.changed_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total changes for an entity
     */
    public function countEntityChanges(string $entityType, string $entityId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.entity_type = :type')
            ->andWhere('c.entity_id = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all changes in a bulk operation
     */
    public function findByRequestId(string $requestId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.request_id = :reqId')
            ->setParameter('reqId', $requestId)
            ->orderBy('c.changed_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(int $userId, \DateTimeInterface $since): array
    {
        $qb = $this->createQueryBuilder('c');
        
        return $qb->select([
                'c.entity_type',
                'c.action',
                'COUNT(c.id) as change_count'
            ])
            ->where('c.user_id = :user')
            ->andWhere('c.changed_at >= :since')
            ->setParameter('user', $userId)
            ->setParameter('since', $since)
            ->groupBy('c.entity_type', 'c.action')
            ->orderBy('change_count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
