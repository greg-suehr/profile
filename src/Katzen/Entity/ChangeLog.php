<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\ChangeLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records who changed what and when on audited entities.
 * 
 * This is an append-only audit log. Never update or delete entries.
 */
#[ORM\Entity(repositoryClass: ChangeLogRepository::class)]
#[ORM\Table(name: 'change_log')]
#[ORM\Index(columns: ['entity_type', 'entity_id', 'changed_at'], name: 'idx_entity_timeline')]
#[ORM\Index(columns: ['user_id', 'changed_at'], name: 'idx_user_activity')]
#[ORM\Index(columns: ['request_id'], name: 'idx_request_group')]
#[ORM\Index(columns: ['changed_at'], name: 'idx_timeline')]
class ChangeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * When this change changed (high precision timestamp)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $changed_at = null;

    /**
     * Who caused this change (KatzenUser ID, or null for system)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $user_id = null;

    /**
     * Groups changes within a single HTTP request or bulk operation
     */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private ?string $request_id = null;

    /**
     * Entity class name (short form, e.g. 'Customer', 'Order', 'Purchase')
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $entity_type = null;

    /**
     * Entity identifier (as string to support composite/non-int keys)
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $entity_id = null;

    /**
     * Type of change: insert, update, delete, assoc, dissoc
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $action = null;

    /**
     * JSONB field changes:
     * - insert: { field: newValue, ... }
     * - update: { field: [oldValue, newValue], ... }
     * - delete: { field: oldValue, ... }
     * - assoc/dissoc: { collection: { added: [...], removed: [...] } }
     */
    #[ORM\Column(type: Types::JSON)]
    private ?array $diff = null;

    /**
     * Request metadata: route, IP, user agent, etc.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChangedAt(): ?\DateTimeImmutable
    {
        return $this->changed_at;
    }

    public function setChangedAt(\DateTimeImmutable $changed_at): static
    {
        $this->changed_at = $changed_at;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(?int $user_id): static
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->request_id;
    }

    public function setRequestId(string $request_id): static
    {
        $this->request_id = $request_id;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entity_type;
    }

    public function setEntityType(string $entity_type): static
    {
        $this->entity_type = strtolower($entity_type);
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entity_id;
    }

    public function setEntityId(string $entity_id): static
    {
        $this->entity_id = $entity_id;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getDiff(): ?array
    {
        return $this->diff;
    }

    public function setDiff(array $diff): static
    {
        $this->diff = $diff;
        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;
        return $this;
    }
}
