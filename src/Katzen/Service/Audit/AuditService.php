<?php

namespace App\Katzen\Service\Audit;

use App\Katzen\Entity\ChangeLog;
use App\Katzen\Repository\ChangeLogRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

/**
 * Public API for querying and managing audit logs.
 * 
 * The actual capture of changes happens via AuditSubscriber listening to Doctrine events.
 * This service provides query/reporting capabilities over the captured data.
 */
final class AuditService
{
    public function __construct(
        private ChangeLogRepository $changeLogRepo,
        private Connection $db,
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    /**
     * Get all changes for a specific entity
     */
    public function getEntityHistory(string $entityType, string $entityId, int $limit = 50): array
    {
        
        return $this->changeLogRepo->findBy(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            ['changed_at' => 'DESC'],
            $limit
        );
    }

    /**
     * Get recent changes by a specific user
     */
    public function getUserActivity(int $userId, int $limit = 100): array
    {
        return $this->changeLogRepo->findBy(
            ['user_id' => $userId],
            ['changed_at' => 'DESC'],
            $limit
        );
    }

    /**
     * Get all changes within a single request (bulk operation)
     */
    public function getRequestChanges(string $requestId): array
    {
        return $this->changeLogRepo->findBy(
            ['request_id' => $requestId],
            ['changed_at' => 'ASC']
        );
    }

    /**
     * Get changes within a date range
     */
    public function getChangesBetween(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?string $entityType = null,
        ?int $userId = null
    ): array {
        $qb = $this->changeLogRepo->createQueryBuilder('c')
            ->where('c.changed_at >= :start')
            ->andWhere('c.changed_at <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.changed_at', 'DESC');

        if ($entityType) {
            $qb->andWhere('c.entity_type = :type')
               ->setParameter('type', $entityType);
        }

        if ($userId) {
            $qb->andWhere('c.user_id = :user')
               ->setParameter('user', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get field-level history for a specific field on an entity
     */
    public function getFieldHistory(string $entityType, string $entityId, string $fieldName): array
    {
        $sql = <<<SQL
            SELECT changed_at, user_id, action, diff, context
            FROM change_log
            WHERE entity_type = :type 
              AND entity_id = :id
              AND diff ? :field
            ORDER BY changed_at DESC
            LIMIT 100
        SQL;

        return $this->db->fetchAllAssociative($sql, [
            'type' => $entityType,
            'id' => $entityId,
            'field' => $fieldName,
        ]);
    }

    /**
     * Get a summary of activity across entity types
     */
    public function getActivitySummary(\DateTimeInterface $since): array
    {
        $sql = <<<SQL
            SELECT 
                entity_type,
                action,
                COUNT(*) as change_count,
                COUNT(DISTINCT user_id) as unique_users,
                MIN(changed_at) as first_change,
                MAX(changed_at) as last_change
            FROM change_log
            WHERE changed_at >= :since
            GROUP BY entity_type, action
            ORDER BY change_count DESC
        SQL;

        return $this->db->fetchAllAssociative($sql, ['since' => $since->format('Y-m-d H:i:s')]);
    }

    /**
     * Find who last modified a specific field
     */
    public function getLastModifier(string $entityType, string $entityId, string $fieldName): ?array
    {
        $sql = <<<SQL
            SELECT changed_at, user_id, diff->:field as field_change
            FROM change_log
            WHERE entity_type = :type 
              AND entity_id = :id
              AND diff ? :field
            ORDER BY changed_at DESC
            LIMIT 1
        SQL;

        $result = $this->db->fetchAssociative($sql, [
            'type' => $entityType,
            'id' => $entityId,
            'field' => $fieldName,
        ]);

        return $result ?: null;
    }

    /**
     * Reconstruct entity state at a specific point in time
     * 
     * This walks backward through changes to rebuild what an entity looked like.
     * Note: This is expensive and should be used sparingly.
     */
    public function reconstructStateAt(
        string $entityType,
        string $entityId,
        \DateTimeInterface $asOf
    ): ?array {
        $changes = $this->changeLogRepo->createQueryBuilder('c')
            ->where('c.entity_type = :type')
            ->andWhere('c.entity_id = :id')
            ->andWhere('c.changed_at <= :asOf')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->setParameter('asOf', $asOf)
            ->orderBy('c.changed_at', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($changes)) {
            return null;
        }

        $state = [];

        foreach ($changes as $change) {
            $diff = $change->getDiff();
            
            switch ($change->getAction()) {
                case 'insert':
                    // Initial state
                    foreach ($diff as $field => $value) {
                        $state[$field] = $value;
                    }
                    break;

                case 'update':
                    // Apply changes
                    foreach ($diff as $field => $value) {
                        if (is_array($value) && count($value) === 2) {
                            // [old, new] format
                            $state[$field] = $value[1];
                        } else {
                            $state[$field] = $value;
                        }
                    }
                    break;

                case 'delete':
                    // Entity was deleted at this point
                    $state['__deleted'] = true;
                    break;
            }
        }

        return $state;
    }

    /**
     * Get current user ID (for manual audit logging if needed)
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return null;
        }
        return $user->getId();
    }

    /**
     * Get current request ID (for grouping bulk operations)
     */
    public function getCurrentRequestId(): string
    {
        $req = $this->requestStack->getCurrentRequest();
        return $req?->attributes->get('request_id') 
            ?? $req?->headers->get('X-Request-Id') 
            ?? bin2hex(random_bytes(8));
    }
}
