<?php

namespace App\Katzen\Component\PanelView;

/**
 * PanelView - Main container for displaying cards in a filterable grid
 * 
 * Features:
 * - Quick-toggle group filters
 * - Search/filter cards
 * - Bulk selection and actions
 * - Responsive grid layout
 */
class PanelView
{
    private string $id;
    private array $cards = [];
    private array $groups = [];
    private array $bulkActions = [];
    private ?string $activeGroup = null;
    
    // Display options
    private bool $selectable = false;
    private ?string $searchPlaceholder = null;
    private ?string $emptyState = null;
    
    private function __construct(string $id)
    {
        $this->id = $id;
    }
    
    public static function create(string $id): self
    {
        return new self($id);
    }
    
    // === CARDS ===
    
    public function addCard(PanelCard $card): self
    {
        $this->cards[] = $card;
        return $this;
    }
    
    public function setCards(array $cards): self
    {
        $this->cards = $cards;
        return $this;
    }
    
    // === GROUPS ===
    
    public function addGroup(PanelGroup $group): self
    {
        $this->groups[] = $group;
        return $this;
    }
    
    public function setActiveGroup(?string $groupKey): self
    {
        $this->activeGroup = $groupKey;
        return $this;
    }
    
    // === BULK ACTIONS ===
    
    public function addBulkAction(PanelAction $action): self
    {
        $this->bulkActions[] = $action->asBulkAction();
        return $this;
    }
    
    // === DISPLAY OPTIONS ===
    
    public function setSelectable(bool $selectable): self
    {
        $this->selectable = $selectable;
        return $this;
    }
    
    public function setSearchPlaceholder(string $placeholder): self
    {
        $this->searchPlaceholder = $placeholder;
        return $this;
    }
    
    public function setEmptyState(string $message): self
    {
        $this->emptyState = $message;
        return $this;
    }
    
    // === FILTERING ===
    
    /**
     * Apply group filter and compute match counts
     */
    private function applyGroupFilter(): array
    {
        // Reset all group counts
        foreach ($this->groups as $group) {
            $group->resetMatchCount();
        }
        
        // If no active group, show all cards
        if ($this->activeGroup === null) {
            return $this->cards;
        }
        
        // Find active group
        $activeGroup = null;
        foreach ($this->groups as $group) {
            if ($group->getKey() === $this->activeGroup) {
                $activeGroup = $group;
                break;
            }
        }
        
        if (!$activeGroup) {
            return $this->cards;
        }
        
        // Filter cards
        $filtered = [];
        foreach ($this->cards as $card) {
            if ($activeGroup->matches($card)) {
                $filtered[] = $card;
                $activeGroup->incrementMatchCount();
            }
        }
        
        return $filtered;
    }
    
    /**
     * Compute match counts for all groups (for badge display)
     */
    private function computeGroupCounts(): void
    {
        foreach ($this->groups as $group) {
            $group->resetMatchCount();
            
            foreach ($this->cards as $card) {
                if ($group->matches($card)) {
                    $group->incrementMatchCount();
                }
            }
        }
    }
    
    // === BUILD ===
    
    /**
     * Build the final panel configuration
     */
    public function build(): array
    {
        // Compute group counts
        $this->computeGroupCounts();
        
        // Apply active group filter
        $visibleCards = $this->applyGroupFilter();
        
        return [
            'id' => $this->id,
            'cards' => array_map(fn($c) => $c->toArray(), $visibleCards),
            'groups' => array_map(fn($g) => $g->toArray(), $this->groups),
            'activeGroup' => $this->activeGroup,
            'bulkActions' => array_map(fn($a) => $a->toArray(), $this->bulkActions),
            'selectable' => $this->selectable,
            'searchPlaceholder' => $this->searchPlaceholder,
            'emptyState' => $this->emptyState,
        ];
    }
}
