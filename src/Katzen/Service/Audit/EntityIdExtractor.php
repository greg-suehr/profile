<?php

namespace App\Katzen\Service\Audit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Extracts entity identifiers as strings, handling simple and composite keys.
 */
final class EntityIdExtractor
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Extract ID from an entity as a string
     */
    public function extractId(object $entity): string
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        $identifiers = $metadata->getIdentifierValues($entity);

        if (empty($identifiers)) {
            // Entity not yet persisted (no ID)
            return 'new';
        }

        if (count($identifiers) === 1) {
            // Simple ID
            return (string) reset($identifiers);
        }

        // Composite key: serialize as JSON
        return json_encode($identifiers, JSON_THROW_ON_ERROR);
    }

    /**
     * Get the short entity type name (e.g., 'Customer' instead of 'App\Katzen\Entity\Customer')
     */
    public function extractType(object $entity): string
    {
        $className = get_class($entity);
        
        // Handle Doctrine proxies
        if (str_contains($className, '\\__CG__\\')) {
            $className = get_parent_class($entity);
        }

        // Get short name
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Get full entity identifier info
     */
    public function extractEntityInfo(object $entity): array
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        
        return [
            'type' => $this->extractType($entity),
            'id' => $this->extractId($entity),
            'full_class' => $metadata->getName(),
            'identifier_fields' => $metadata->getIdentifierFieldNames(),
        ];
    }
}
