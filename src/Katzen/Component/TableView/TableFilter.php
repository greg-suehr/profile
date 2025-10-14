<?php

namespace App\Katzen\Component\TableView;

/**
 * TableFilter - Defines advanced filters (to be implemented later)
 * 
 * This is a placeholder for future filter panel implementation
 */
class TableFilter
{
    private string $name;
    private string $label;
    private string $type;
    private array $options = [];
    private mixed $defaultValue = null;
    
    private function __construct(string $name, string $label, string $type)
    {
        $this->name = $name;
        $this->label = $label;
        $this->type = $type;
    }
    
    public static function select(string $name, string $label, array $options): self
    {
        $filter = new self($name, $label, 'select');
        $filter->options = $options;
        return $filter;
    }
    
    public static function range(string $name, string $label): self
    {
        return new self($name, $label, 'range');
    }
    
    public static function date(string $name, string $label): self
    {
        return new self($name, $label, 'date');
    }
    
    public function setDefaultValue(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }
    
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'defaultValue' => $this->defaultValue,
        ];
    }
}