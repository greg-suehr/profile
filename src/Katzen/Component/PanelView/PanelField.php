<?php

namespace App\Katzen\Component\PanelView;

/**
 * PanelField - Defines how to display a data field in a PanelCard
 * 
 * Similar to TableField, extracts values from card data and formats them for display.
 * Can be used in default card body or referenced in custom Twig templates.
 */
class PanelField
{
    private string $key;
    private string $label;
    private string $type;
    private array $options = [];
    
    // Display options
    private bool $muted = false;
    private ?string $icon = null;
    private ?string $align = null;
    
    private function __construct(string $key, string $label, string $type)
    {
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
    }
    
    // === FACTORY METHODS ===
    
    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'text');
    }
    
    public static function badge(string $key, string $label): self
    {
        return new self($key, $label, 'badge');
    }

    public static function currency(string $key, string $label): self
    {
        return new self($key, $label, 'currency');
    }
    
    public static function date(string $key, string $label, string $format = 'Y-m-d'): self
    {
        $field = new self($key, $label, 'date');
        $field->options['format'] = $format;
        return $field;
    }
    
    public static function duration(string $key, string $label): self
    {
        return new self($key, $label, 'duration');
    }
    
    public static function number(string $key, string $label, int $decimals = 0): self
    {
        $field = new self($key, $label, 'number');
        $field->options['decimals'] = $decimals;
        return $field;
    }
    
    public static function custom(string $key, string $label, callable $formatter): self
    {
        $field = new self($key, $label, 'custom');
        $field->options['formatter'] = $formatter;
        return $field;
    }
    
    // === CONFIGURATION ===
    
    public function badgeMap(array $map): self
    {
        $this->options['badgeMap'] = $map;
        return $this;
    }
    
    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;
        return $this;
    }
    
    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }
    
    public function align(string $align): self
    {
        $this->align = $align;
        return $this;
    }
    
    // === VALUE EXTRACTION ===
    
    /**
     * Extract and format the value from card data
     */
    public function getValue(array $data): mixed
    {
        $raw = $data[$this->key] ?? null;
        
        if ($raw === null) {
            return null;
        }
        
        return match($this->type) {
            'text' => (string) $raw,
            'number' => number_format((float) $raw, $this->options['decimals'] ?? 0),
            'date' => $this->formatDate($raw),
            'duration' => $this->formatDuration($raw),
            'badge' => (string) $raw,
            'custom' => ($this->options['formatter'])($raw, $data),
            default => (string) $raw,
        };
    }
    
    /**
     * Get the badge variant for a value
     */
    public function getBadgeVariant(mixed $value): string
    {
        if (!isset($this->options['badgeMap'])) {
            return 'secondary';
        }
        
        return $this->options['badgeMap'][$value] ?? 'secondary';
    }
    
    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->options['format'] ?? 'Y-m-d');
        }
        
        return (string) $value;
    }
    
    private function formatDuration(mixed $value): string
    {
        if (is_numeric($value)) {
            $hours = floor($value / 60);
            $minutes = $value % 60;
            
            if ($hours > 0) {
                return sprintf('%dh %dm', $hours, $minutes);
            }
            return sprintf('%dm', $minutes);
        }
        
        return (string) $value;
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
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function isMuted(): bool
    {
        return $this->muted;
    }
    
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    public function getAlign(): ?string
    {
        return $this->align;
    }
    
    public function getOptions(): array
    {
        return $this->options;
    }
    
    // === SERIALIZATION ===
    
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'muted' => $this->muted,
            'icon' => $this->icon,
            'align' => $this->align,
            'options' => $this->options,
        ];
    }
}
