<?php

namespace App\Katzen\Component\TableView;

/**
 * TableField - Defines a column in the table
 * 
 * Supports types: text, duration, currency, badge, status, date, link
 */
class TableField
{
    private string $key;
    private string $label;
    private string $type;
    private bool $sortable = false;
    private bool $hiddenMobile = false;
    private string $align = 'left';
    private ?array $badgeMap = null;
    private ?string $format = null;
    private ?string $linkRoute = null;
    private array $linkParams = [];
    
    private function __construct(string $key, string $label, string $type)
    {
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
    }
    
    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'text');
    }
    
    public static function badge(string $key, string $label): self
    {
        return new self($key, $label, 'badge');
    }
    
    public static function status(string $key, string $label): self
    {
        return new self($key, $label, 'status');
    }
    
    public static function currency(string $key, string $label): self
    {
        return new self($key, $label, 'currency');
    }
    
    public static function duration(string $key, string $label): self
    {
        return new self($key, $label, 'duration');
    }
    
    public static function date(string $key, string $label, string $format = 'Y-m-d'): self
    {
        $field = new self($key, $label, 'date');
        $field->format = $format;
        return $field;
    }
    
    public static function link(string $key, string $label, string $route, array $params = []): self
    {
        $field = new self($key, $label, 'link');
        $field->linkRoute = $route;
        $field->linkParams = $params;
        return $field;
    }
    
    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;
        return $this;
    }
    
    public function hiddenMobile(bool $hidden = true): self
    {
        $this->hiddenMobile = $hidden;
        return $this;
    }
    
    public function align(string $align): self
    {
        if (!in_array($align, ['left', 'center', 'right'])) {
            throw new \InvalidArgumentException("Invalid alignment: $align");
        }
        $this->align = $align;
        return $this;
    }
    
    /**
     * Set badge color mapping for badge/status types
     * 
     * Example: ['active' => 'success', 'draft' => 'warning', 'archived' => 'secondary']
     */
    public function badgeMap(array $map): self
    {
        $this->badgeMap = $map;
        return $this;
    }
    
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }
    
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => $this->sortable,
            'hiddenMobile' => $this->hiddenMobile,
            'align' => $this->align,
            'badgeMap' => $this->badgeMap,
            'format' => $this->format,
            'linkRoute' => $this->linkRoute,
            'linkParams' => $this->linkParams,
        ];
    }
}