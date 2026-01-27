<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\SellableVariant;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Entity Map - Maps import identifiers to created database entities.
 * 
 * During import, we need to resolve references between entities:
 * - OrderItem references a Sellable (by name/SKU from import)
 * - Order references a Customer (by name/email from import)
 * - Order references a StockLocation (by name/store_id from import)
 * 
 * This value object tracks all entities created during master data extraction
 * and allows the transaction importer to resolve these references by their
 * original import keys (names, SKUs, external IDs, etc.)
 * 
 * @example
 * ```php
 * # During master data creation:
 * $entityMap->add('sellable', 'Latte Sm', $sellable->getId());
 * $entityMap->add('stock_location', 'Store #42', $location->getId());
 * 
 * # During transaction import:
 * $sellableId = $entityMap->get('sellable', 'Latte Sm');
 * $sellable = $entityMap->getEntity('sellable', 'Latte Sm', $em);
 * ```
 */
final class EntityMap
{
  /**
   * Maps of entity type -> external key -> entity ID
   * 
   * @var array<string, array<string, int>>
   */
  private array $idMaps = [];
    
  /**
   * Maps of entity type -> external key -> entity object (cached)
   * 
   * @var array<string, array<string, object>>
   */
  private array $entityCache = [];
    
  /**
   * Alternate keys for the same entity (SKU -> name, external_id -> name, etc.)
   * 
   * @var array<string, array<string, string>>
   */
  private array $aliases = [];
    
  /**
   * Statistics about map operations
   */
  private array $stats = [
    'adds' => 0,
    'gets' => 0,
    'hits' => 0,
    'misses' => 0,
  ];
    
  public function __construct()
  {
    foreach ($this->getSupportedEntityTypes() as $type) {
      $this->idMaps[$type] = [];
      $this->entityCache[$type] = [];
      $this->aliases[$type] = [];
    }
  }
    
  /**
   * Get supported entity types
   */
    public function getSupportedEntityTypes(): array
  {
    return [
      'stock_location',
      'sellable',
      'sellable_variant',
      'customer',
      'vendor',
      'item',
      'item_variant',
    ];
  }
    
  /**
   * Add a mapping from external key to entity ID.
   * 
   * @param string $entityType Type identifier (e.g., 'sellable', 'customer')
   * @param string $externalKey Import identifier (name, SKU, external_id)
   * @param int $entityId Database entity ID
   * @param object|null $entity Optionally cache the entity object too
   */
  public function add(
    string $entityType,
    string $externalKey,
    int $entityId,
    ?object $entity = null
  ): void {
    $this->validateEntityType($entityType);
    $normalizedKey = $this->normalizeKey($externalKey);
    
    $this->idMaps[$entityType][$normalizedKey] = $entityId;
        
    if ($entity !== null) {
      $this->entityCache[$entityType][$normalizedKey] = $entity;
    }
    
    $this->stats['adds']++;
  }
    
  /**
   * Add an alias (alternate key) for an entity.
   * 
   * Useful when the same entity can be referenced by different fields
   * (e.g., both SKU and product name).
   * 
   * @param string $entityType Entity type
   * @param string $alias The alternate key
   * @param string $primaryKey The primary key that was used in add()
   */
  public function addAlias(string $entityType, string $alias, string $primaryKey): void
  {
    $this->validateEntityType($entityType);
    $this->aliases[$entityType][$this->normalizeKey($alias)] = $this->normalizeKey($primaryKey);
  }
    
  /**
   * Get entity ID by external key.
   * 
   * @param string $entityType Entity type
   * @param string $externalKey Import identifier
   * @return int|null Entity ID or null if not found
   */
  public function get(string $entityType, string $externalKey): ?int
  {
    $this->validateEntityType($entityType);
    $normalizedKey = $this->normalizeKey($externalKey);
        
    $this->stats['gets']++;
    
    if (isset($this->idMaps[$entityType][$normalizedKey])) {
      $this->stats['hits']++;
      return $this->idMaps[$entityType][$normalizedKey];
    }
    
    if (isset($this->aliases[$entityType][$normalizedKey])) {
      $primaryKey = $this->aliases[$entityType][$normalizedKey];
      if (isset($this->idMaps[$entityType][$primaryKey])) {
        $this->stats['hits']++;
        return $this->idMaps[$entityType][$primaryKey];
      }
    }
        
    $this->stats['misses']++;
    return null;
  }
    
  /**
   * Check if an entity exists in the map.
   */
  public function has(string $entityType, string $externalKey): bool
  {
    return $this->get($entityType, $externalKey) !== null;
  }
    
  /**
   * Get entity object by external key.
   * 
   * If entity was cached during add(), returns from cache.
   * Otherwise, fetches from database using EntityManager.
   * 
   * @param string $entityType Entity type
   * @param string $externalKey Import identifier
   * @param EntityManagerInterface $em Entity manager for DB lookup
   * @return object|null Entity or null if not found
   */
  public function getEntity(
    string $entityType,
    string $externalKey,
    EntityManagerInterface $em
  ): ?object {
    $this->validateEntityType($entityType);
    $normalizedKey = $this->normalizeKey($externalKey);
    
    if (isset($this->aliases[$entityType][$normalizedKey])) {
      $normalizedKey = $this->aliases[$entityType][$normalizedKey];
    }
    
    if (isset($this->entityCache[$entityType][$normalizedKey])) {
      return $this->entityCache[$entityType][$normalizedKey];
    }
    
    $entityId = $this->idMaps[$entityType][$normalizedKey] ?? null;
    if ($entityId === null) {
      return null;
    }
    
    $entityClass = $this->getEntityClass($entityType);
    $entity = $em->find($entityClass, $entityId);
    
    if ($entity !== null) {
      $this->entityCache[$entityType][$normalizedKey] = $entity;
    }
    
    return $entity;
  }
    
  /**
   * Get all mappings for an entity type.
   * 
   * @return array<string, int> Map of external key -> entity ID
   */
  public function getAll(string $entityType): array
  {
    $this->validateEntityType($entityType);
    return $this->idMaps[$entityType];
  }
    
  /**
   * Get count of mappings for an entity type.
   */
  public function count(string $entityType): int
  {
    $this->validateEntityType($entityType);
    return count($this->idMaps[$entityType]);
  }
  
  /**
   * Get total count across all entity types.
   */
  public function totalCount(): int
  {
    $total = 0;
    foreach ($this->idMaps as $map) {
      $total += count($map);
    }
    return $total;
  }
    
  /**
   * Get mapping statistics.
   */
  public function getStats(): array
  {
    return [
      ...$this->stats,
      'hit_rate' => $this->stats['gets'] > 0 
        ? round($this->stats['hits'] / $this->stats['gets'] * 100, 2) 
        : 0,
            'by_type' => array_map(
              fn($map) => count($map),
              $this->idMaps
        ),
    ];
  }
    
  /**
   * Clear all mappings (useful for testing or batch resets).
   */
  public function clear(): void
  {
    foreach ($this->getSupportedEntityTypes() as $type) {
      $this->idMaps[$type] = [];
      $this->entityCache[$type] = [];
      $this->aliases[$type] = [];
    }
    $this->stats = ['adds' => 0, 'gets' => 0, 'hits' => 0, 'misses' => 0];
  }
    
  /**
   * Merge another EntityMap into this one.
   */
  public function merge(EntityMap $other): void
  {
    foreach ($other->getSupportedEntityTypes() as $type) {
      foreach ($other->getAll($type) as $key => $id) {
        $this->idMaps[$type][$key] = $id;
      }
    }
  }
    
  /**
   * Normalize external key for consistent lookup.
   */
  private function normalizeKey(string $key): string
  {
    return strtolower(trim($key));
  }
    
  /**
   * Validate entity type is supported.
   */
  private function validateEntityType(string $entityType): void
  {
    if (!in_array($entityType, $this->getSupportedEntityTypes(), true)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Unknown entity type "%s". Supported: %s',
          $entityType,
          implode(', ', $this->getSupportedEntityTypes())
            )
        );
    }
  }
  
  /**
   * Get entity class for type.
   */
  private function getEntityClass(string $entityType): string
  {
    return match ($entityType) {
      'stock_location' => StockLocation::class,
      'sellable' => Sellable::class,
      'sellable_variant' => SellableVariant::class,
      'customer' => Customer::class,
      'vendor' => Vendor::class,
      'item' => Item::class,
      'item_variant' => Item::class,
      default => throw new \InvalidArgumentException("No class mapping for: {$entityType}"),
    };
  }
    
  /**
   * Create a snapshot of current state (for debugging/logging).
   */
  public function toArray(): array
  {
    return [
      'mappings' => $this->idMaps,
      'alias_count' => array_map(fn($a) => count($a), $this->aliases),
      'stats' => $this->getStats(),
    ];
  }
}
