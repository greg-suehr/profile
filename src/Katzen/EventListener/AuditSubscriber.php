<?php

namespace App\Katzen\EventListener;

use App\Katzen\Service\Audit\AuditConfig;
use App\Katzen\Service\Audit\EntityIdExtractor;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

/**
 * Captures entity changes and writes them to the audit log.
 * 
 * This subscriber listens to Doctrine's onFlush and postFlush events,
 * buffers changes during the transaction, then writes them all at once
 * after the main transaction commits.
 */
#[AsDoctrineListener(event: Events::onFlush, priority: 0)]
#[AsDoctrineListener(event: Events::postFlush, priority: 0)]
final class AuditSubscriber implements EventSubscriber
{
    /** @var array<array<string, mixed>> */
    private array $buffer = [];

    public function __construct(
        private Connection $db,
        private Security $security,
        private RequestStack $requests,
        private EntityIdExtractor $ids,
        private AuditConfig $cfg
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    /**
     * Buffer all changes during the flush operation
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $userId = $this->getUserId();
        $req = $this->requests->getCurrentRequest();
        $requestId = $this->getRequestId($req);
        $context = $this->makeContext($req);

        // INSERTS
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            [$type, $id, $diff] = $this->snapshotInsert($uow, $entity);
            if ($this->isEmpty($type, $diff)) {
                continue;
            }
            $this->buffer[] = $this->row($userId, $requestId, $type, $id, 'insert', $diff, $context);
        }

        // UPDATES
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            [$type, $id, $diff] = $this->snapshotUpdate($uow, $entity);
            if ($this->isEmpty($type, $diff)) {
                continue;
            }
            $this->buffer[] = $this->row($userId, $requestId, $type, $id, 'update', $diff, $context);
        }

        // DELETES
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            [$type, $id, $diff] = $this->snapshotDelete($uow, $entity);
            if ($this->isEmpty($type, $diff)) {
                continue;
            }
            $this->buffer[] = $this->row($userId, $requestId, $type, $id, 'delete', $diff, $context);
        }

        // COLLECTION CHANGES (many-to-many, one-to-many owning side)
        foreach ($uow->getScheduledCollectionUpdates() as $coll) {
            [$type, $id, $field, $added, $removed] = $this->snapshotCollection($coll);
            if (!$type || (empty($added) && empty($removed))) {
                continue;
            }
            
            $this->buffer[] = $this->row($userId, $requestId, $type, $id, 'update', [
                $field => ['added' => $added, 'removed' => $removed],
            ], $context);
        }
    }

    /**
     * Write all buffered changes after the main transaction commits
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $sql = <<<SQL
            INSERT INTO change_log (
                changed_at, user_id, request_id, 
                entity_type, entity_id, action, 
                diff, context
            ) VALUES (
                :ts, :user, :req, 
                :type, :id, :act, 
                :diff, :ctx
            )
        SQL;

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        // Use a separate connection/transaction for audit writes
        // This prevents audit failures from rolling back business transactions
        $this->db->beginTransaction();
        
        try {
            foreach ($this->buffer as $row) {
                $this->db->executeStatement($sql, [
                    'ts' => $now,
                    'user' => $row['user_id'],
                    'req' => $row['request_id'],
                    'type' => $row['entity_type'],
                    'id' => $row['entity_id'],
                    'act' => $row['action'],
                    'diff' => json_encode($row['diff'], JSON_THROW_ON_ERROR),
                    'ctx' => json_encode($row['context'], JSON_THROW_ON_ERROR),
                ]);
            }
            
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            
            // DECISION POINT: Swallow or rethrow?
            // Most ERPs choose to log the error and continue to avoid blocking business ops
            // For strict audit requirements, you might want to rethrow
            
            error_log('Audit log write failed: ' . $e->getMessage());
            
            // Optionally: notify administrators, trigger alerts, etc.
            // throw $e; // Uncomment for strict audit mode
        } finally {
            $this->buffer = [];
        }
    }

    /**
     * Snapshot entity state on insert
     */
    private function snapshotInsert($uow, object $entity): array
    {
        $type = $this->ids->extractType($entity);
        
        if (!$this->cfg->shouldAuditEntity($type)) {
            return [$type, null, []];
        }

        $id = $this->ids->extractId($entity);
        $changeset = $uow->getEntityChangeSet($entity);
        
        // For inserts, we only care about the new values
        $diff = [];
        foreach ($changeset as $field => [$old, $new]) {
            if ($this->cfg->shouldAuditField($type, $field)) {
                $diff[$field] = $this->serializeValue($new);
            }
        }

        return [$type, $id, $diff];
    }

    /**
     * Snapshot entity state on update
     */
    private function snapshotUpdate($uow, object $entity): array
    {
        $type = $this->ids->extractType($entity);
        
        if (!$this->cfg->shouldAuditEntity($type)) {
            return [$type, null, []];
        }

        $id = $this->ids->extractId($entity);
        $changeset = $uow->getEntityChangeSet($entity);
        
        // For updates, track both old and new values
        $diff = [];
        foreach ($changeset as $field => [$old, $new]) {
            if ($this->cfg->shouldAuditField($type, $field)) {
                $diff[$field] = [
                    $this->serializeValue($old),
                    $this->serializeValue($new),
                ];
            }
        }

        return [$type, $id, $diff];
    }

    /**
     * Snapshot entity state on delete
     */
    private function snapshotDelete($uow, object $entity): array
    {
        $type = $this->ids->extractType($entity);
        
        if (!$this->cfg->shouldAuditEntity($type)) {
            return [$type, null, []];
        }

        $id = $this->ids->extractId($entity);
        
        // Capture final state before deletion
        $metadata = $uow->getEntityPersister(get_class($entity));
        $diff = [];
        
        // Get entity data from identity map
        $originalData = $uow->getOriginalEntityData($entity);
        
        foreach ($originalData as $field => $value) {
            if ($this->cfg->shouldAuditField($type, $field)) {
                $diff[$field] = $this->serializeValue($value);
            }
        }

        return [$type, $id, $diff];
    }

    /**
     * Snapshot collection changes (associations)
     */
    private function snapshotCollection($collection): array
    {
        $owner = $collection->getOwner();
        if (!$owner) {
            return [null, null, null, [], []];
        }

        $type = $this->ids->extractType($owner);
        
        if (!$this->cfg->shouldAuditEntity($type)) {
            return [$type, null, null, [], []];
        }

        $id = $this->ids->extractId($owner);
        $mapping = $collection->getMapping();
        $field = $mapping['fieldName'];

        if (!$this->cfg->shouldAuditField($type, $field)) {
            return [$type, $id, $field, [], []];
        }

        $added = [];
        $removed = [];

        foreach ($collection->getInsertDiff() as $element) {
            $added[] = $this->serializeEntityRef($element);
        }

        foreach ($collection->getDeleteDiff() as $element) {
            $removed[] = $this->serializeEntityRef($element);
        }

        return [$type, $id, $field, $added, $removed];
    }

    /**
     * Serialize a value for storage in JSON
     */
    private function serializeValue($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return $this->serializeEntityRef($value);
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->serializeValue($v), $value);
        }

        return (string) $value;
    }

    /**
     * Serialize an entity reference for storage
     */
    private function serializeEntityRef(object $entity): string
    {
        $type = $this->ids->extractType($entity);
        $id = $this->ids->extractId($entity);
        
        return "{$type}:{$id}";
    }

    /**
     * Get current user (user) ID
     */
    private function getUserId(): ?int
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return null; // System/background operation
        }

        if (method_exists($user, 'getId')) {
            return $user->getId();
        }

        return null;
    }

    /**
     * Get or generate request ID for grouping bulk operations
     */
    private function getRequestId($req): string
    {
        if (!$req) {
            return 'system-' . bin2hex(random_bytes(8));
        }

        return $req->attributes->get('request_id')
            ?? $req->headers->get('X-Request-Id')
            ?? 'req-' . bin2hex(random_bytes(8));
    }

    /**
     * Build context metadata from request
     */
    private function makeContext($req): array
    {
        if (!$req) {
            return ['source' => 'background'];
        }

        return [
            'route' => $req->attributes->get('_route', 'unknown'),
            'method' => $req->getMethod(),
            'ip' => $req->getClientIp(),
            'user_agent' => $req->headers->get('User-Agent'),
        ];
    }

    /**
     * Build a row for the buffer
     */
    private function row(?int $userId, string $requestId, string $type, string $id, string $action, array $diff, array $context): array
    {
        return [
            'user_id' => $userId,
            'request_id' => $requestId,
            'entity_type' => $type,
            'entity_id' => $id,
            'action' => $action,
            'diff' => $diff,
            'context' => $context,
        ];
    }

    /**
     * Check if a changeset is empty (nothing to audit)
     */
    private function isEmpty(string $type, array $diff): bool
    {
        return empty($diff);
    }
}
