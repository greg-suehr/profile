<?php

namespace App\Katzen\ValueObject;

/**
 * LocationScope - Represents a user's current location filtering context
 * 
 * This value object encapsulates the three modes of location filtering:
 * - single: One specific location
 * - multi: Multiple specific locations
 * - all: All locations (no filter)
 * 
 * Usage:
 *   $scope = new LocationScope('single', [3]);
 *   $scope = new LocationScope('multi', [1, 2, 5]);
 *   $scope = new LocationScope('all');
 */
final class LocationScope
{
  public function __construct(
    public readonly string $mode,
    /** @var int[] */
    public readonly array $locationIds = [],
  ) {
    if (!in_array($mode, ['single', 'multi', 'all'], true)) {
      throw new \InvalidArgumentException(
        "Invalid mode '{$mode}'. Must be 'single', 'multi', or 'all'."
      );
    }

    if ($mode === 'single' && count($locationIds) !== 1) {
      throw new \InvalidArgumentException(
        'Single mode requires exactly one location ID.'
      );
    }
    
    if ($mode === 'multi' && count($locationIds) < 2) {
      throw new \InvalidArgumentException(
        'Multi mode requires at least two location IDs.'
      );
    }
    
    if ($mode === 'all' && !empty($locationIds)) {
      throw new \InvalidArgumentException(
        'All mode must have no location IDs.'
      );
    }
  }

  public function isSingle(): bool
  {
    return $this->mode === 'single';
  }

  public function isMulti(): bool
  {
    return $this->mode === 'multi';
  }

  public function isAll(): bool
  {
    return $this->mode === 'all';
  }

  /**
   * Get the single location ID (only valid in single mode)
   */
  public function getSingleLocationId(): ?int
  {
    return $this->isSingle() ? $this->locationIds[0] : null;
  }

  /**
   * Check if a specific location is included in this scope
   */
  public function includesLocation(int $locationId): bool
  {
    if ($this->isAll()) {
      return true;
    }
        
    return in_array($locationId, $this->locationIds, true);
  }

  /**
   * Serialize to array for user preferences storage
   */
  public function toArray(): array
  {
    return [
      'mode' => $this->mode,
      'location_ids' => $this->locationIds,
    ];
  }

  /**
   * Create from array (e.g., from user preferences)
   */
  public static function fromArray(array $data): self
  {
    $mode = $data['mode'] ?? 'all';
    $locationIds = $data['location_ids'] ?? [];
    
    return new self($mode, $locationIds);
  }
}
