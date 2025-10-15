<?php

namespace App\Katzen\Component\TableView;

/**
 * TableAction - Defines actions (quick actions per row, or bulk actions)
 */
class TableAction
{
    private string $name;
    private string $label;
    private string $icon;
    private string $variant;
    private ?string $route = null;
    private array $routeParams = [];
    private ?string $confirmMessage = null;
    private bool $requiresSelection = false;
    
    private function __construct(string $name, string $label)
    {
        $this->name = $name;
        $this->label = $label;
        $this->icon = '';
        $this->variant = 'outline-secondary';
    }
    
    public static function create(string $name, string $label): self
    {
        return new self($name, $label);
    }
    
  public static function view(?string $route): self
    {
        $action = new self('view', 'View');
        $action->icon = 'bi-eye-fill';
        $action->variant = 'outline-primary';
        if ($route) {
            $action->route = $route;
        }
        return $action;
    }
    
    public static function edit(?string $route): self
    {
        $action = new self('edit', 'Edit');
        $action->icon = 'bi-pencil-fill';
        $action->variant = 'outline-secondary';
        if ($route) {
            $action->route = $route;
        }
        return $action;
    }
    
    public static function delete(?string $route): self
    {
        $action = new self('delete', 'Delete');
        $action->icon = 'bi-trash-fill';
        $action->variant = 'outline-danger';
        $action->confirmMessage = 'Are you sure you want to delete this item?';
        if ($route) {
            $action->route = $route;
        }
        return $action;
    }
    
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }
    
    public function setVariant(string $variant): self
    {
        $this->variant = $variant;
        return $this;
    }
    
    public function setRoute(string $route, array $params = []): self
    {
        $this->route = $route;
        $this->routeParams = $params;
        return $this;
    }
    
    public function setConfirmMessage(string $message): self
    {
        $this->confirmMessage = $message;
        return $this;
    }
    
    public function requiresSelection(bool $required = true): self
    {
        $this->requiresSelection = $required;
        return $this;
    }
    
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'route' => $this->route,
            'routeParams' => $this->routeParams,
            'confirmMessage' => $this->confirmMessage,
            'requiresSelection' => $this->requiresSelection,
        ];
    }
}
