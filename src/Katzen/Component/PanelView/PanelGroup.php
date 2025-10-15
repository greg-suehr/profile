<?php

namespace App\Katzen\Component\PanelView;

/**
 * PanelGroup - Defines a filter group for PanelView
 * 
 * Groups allow quick-toggle filtering of cards using declarative rules
 * with a callback escape hatch for complex conditions.
 */
class PanelGroup
{
    private string $key;
    private string $label;
    private array $rules = [];
    # TODO: fix this
    # private ?\Closure $customfilter = null;
    private ?string $icon = null;
    private int $matchCount = 0;
    
    private function __construct(string $key, string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }
    
    public static function create(string $key, string $label): self
    {
        return new self($key, $label);
    }
    
    // === DECLARATIVE RULES ===
    
    public function whereEquals(string $field, mixed $value): self
    {
        $this->rules[] = [
            'type' => 'equals',
            'field' => $field,
            'value' => $value,
        ];
        return $this;
    }
    
    public function whereIn(string $field, array $values): self
    {
        $this->rules[] = [
            'type' => 'in',
            'field' => $field,
            'values' => $values,
        ];
        return $this;
    }
    
    public function whereNotEquals(string $field, mixed $value): self
    {
        $this->rules[] = [
            'type' => 'not_equals',
            'field' => $field,
            'value' => $value,
        ];
        return $this;
    }
    
    public function whereGreaterThan(string $field, mixed $value): self
    {
        $this->rules[] = [
            'type' => 'gt',
            'field' => $field,
            'value' => $value,
        ];
        return $this;
    }
    
    public function whereLessThan(string $field, mixed $value): self
    {
        $this->rules[] = [
            'type' => 'lt',
            'field' => $field,
            'value' => $value,
        ];
        return $this;
    }
    
    /**
     * Filter by date/time within next X interval
     * Example: ->withinNext('scheduled_at', '2h')
     */
    public function withinNext(string $field, string $interval): self
    {
        $this->rules[] = [
            'type' => 'within_next',
            'field' => $field,
            'interval' => $interval,
        ];
        return $this;
    }
    
    /**
     * Filter by date/time within past X interval  
     * Example: ->withinPast('created_at', '24h')
     */
    public function withinPast(string $field, string $interval): self
    {
        $this->rules[] = [
            'type' => 'within_past',
            'field' => $field,
            'interval' => $interval,
        ];
        return $this;
    }
    
    /**
     * Custom filter callback for complex conditions
     *
     * @param callable(PanelCard):bool $filter
     */
#    public function custom(callable $filter): self
#    {
#        $this->customFilter = $filterinstanceof \Closure
#          ? $filter
#          : \Closure::fromCallable($filter);
#        return $this;
#    }
    
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }
    
    // === EVALUATION ===
    
    /**
     * Test if a card matches this group's filters
     */
    public function matches(PanelCard $card): bool
    {
        $data = $card->getData();
        
        // All declarative rules must pass
        foreach ($this->rules as $rule) {
            if (!$this->evaluateRule($rule, $data)) {
                return false;
            }
        }
        
#        // Custom filter (if set) must also pass
#        if ($this->customFilter !== null) {
#            return ($this->customFilter)($card);
#        }
        
        return true;
    }
    
    private function evaluateRule(array $rule, array $data): bool
    {
        $field = $rule['field'];
        $value = $data[$field] ?? null;
        
        return match($rule['type']) {
            'equals' => $value === $rule['value'],
            'not_equals' => $value !== $rule['value'],
            'in' => in_array($value, $rule['values'], true),
            'gt' => $value !== null && $value > $rule['value'],
            'lt' => $value !== null && $value < $rule['value'],
            'within_next' => $this->evaluateWithinNext($value, $rule['interval']),
            'within_past' => $this->evaluateWithinPast($value, $rule['interval']),
            default => false,
        };
    }
    
    private function evaluateWithinNext(mixed $value, string $interval): bool
    {
        if (!$value instanceof \DateTimeInterface) {
            if (is_string($value)) {
                try {
                    $value = new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        $now = new \DateTimeImmutable();
        $future = $this->addInterval($now, $interval);
        
        return $value >= $now && $value <= $future;
    }
    
    private function evaluateWithinPast(mixed $value, string $interval): bool
    {
        if (!$value instanceof \DateTimeInterface) {
            if (is_string($value)) {
                try {
                    $value = new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        $now = new \DateTimeImmutable();
        $past = $this->subtractInterval($now, $interval);
        
        return $value >= $past && $value <= $now;
    }
    
    private function addInterval(\DateTimeImmutable $date, string $interval): \DateTimeImmutable
    {
        // Parse interval like "2h", "30m", "1d"
        if (preg_match('/^(\d+)([smhdw])$/', $interval, $matches)) {
            $amount = (int) $matches[1];
            $unit = $matches[2];
            
            $spec = match($unit) {
              's' => "PT{$amount}S",
              'm' => "PT{$amount}M",              
              'h' => "PT{$amount}H",
              'd' => "P{$amount}D",
              'w' => "P.($amount * 7)D",              
              default => 'PT0S',
            };
            
            return $date->add(new \DateInterval($spec));
        }
        
        return $date;
    }
    
    private function subtractInterval(\DateTimeImmutable $date, string $interval): \DateTimeImmutable
    {
        // Parse interval like "2h", "30m", "1d"
        if (preg_match('/^(\d+)([hmd])$/', $interval, $matches)) {
            $amount = (int) $matches[1];
            $unit = $matches[2];
            
            $spec = match($unit) {
                'h' => "PT{$amount}H",
                'm' => "PT{$amount}M",
                'd' => "P{$amount}D",
                default => 'PT0S',
            };
            
            return $date->sub(new \DateInterval($spec));
        }
        
        return $date;
    }
    
    // === MATCH TRACKING ===
    
    public function incrementMatchCount(): void
    {
        $this->matchCount++;
    }
    
    public function resetMatchCount(): void
    {
        $this->matchCount = 0;
    }
    
    // === GETTERS ===
    
    public function getKey(): string
    {
        return $this->key;
    }
    
    public function getLabel(): string
    {
        return $this->label;
    }
    
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    public function getMatchCount(): int
    {
        return $this->matchCount;
    }
    
    // === SERIALIZATION ===
    
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'matchCount' => $this->matchCount,
            'rules' => $this->rules,
        ];
    }
}
