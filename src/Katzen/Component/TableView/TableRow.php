<?php

namespace App\Katzen\Component\TableView;

/**
 * TableRow - Represents a single row of data
 * 
 * Can contain styling, links, and custom attributes
 */
class TableRow
{
    private array $data;
    private ?string $id = null;
    private ?string $linkRoute = null;
    private array $linkParams = [];
    private ?string $styleClass = null;
    private array $attributes = [];
    
    private function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public static function create(array $data): self
    {
        return new self($data);
    }
    
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
    
    /**
     * Set row ID for selection tracking
     */
    public function setId(string|int $id): self
    {
        $this->id = (string)$id;
        return $this;
    }
    
    /**
     * Make entire row clickable (full-row link)
     */
    public function setLink(string $route, array $params = []): self
    {
        $this->linkRoute = $route;
        $this->linkParams = $params;
        return $this;
    }
    
    /**
     * Add contextual styling classes
     * 
     * Examples: 'table-muted', 'table-warning', 'text-danger'
     */
    public function setStyleClass(string $class): self
    {
        $this->styleClass = $class;
        return $this;
    }
    
    /**
     * Add custom data attributes for JavaScript interaction
     */
    public function setAttribute(string $key, string $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    /**
     * Get value for a specific field key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Set value for a specific field key
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Build searchable text from all data values for client-side filtering
     */
    public function getSearchableText(): string
    {
        return strtolower(implode(' ', array_map(function($val) {
            if (is_scalar($val)) {
                return (string)$val;
            }
            if ($val instanceof \DateTimeInterface) {
                return $val->format('Y-m-d H:i:s');
            }
            return '';
        }, $this->data)));
    }
    
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'id' => $this->id,
            'linkRoute' => $this->linkRoute,
            'linkParams' => $this->linkParams,
            'styleClass' => $this->styleClass,
            'attributes' => $this->attributes,
            'searchable' => $this->getSearchableText(),
        ];
    }
}