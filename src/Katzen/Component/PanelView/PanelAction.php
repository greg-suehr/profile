<?php

namespace App\Katzen\Component\PanelView;

/**
 * PanelAction - Defines actions available on a PanelCard
 * 
 * Similar to TableAction, but designed for card footers and bulk operations
 */
class PanelAction
{
    private string $action;
    private string $label;
    private string $variant = 'primary';
    private ?string $icon = null;
    private string $httpMethod = 'GET';
    private ?string $route = null;
    private array $routeParams = [];
    private ?string $confirmMessage = null;
    private bool $isBulk = false;
    
    private function __construct(string $action, string $label)
    {
        $this->action = $action;
        $this->label = $label;
        $this->icon = '';
        $this->variant = 'outline-secondary';
    }
    
    // === FACTORY METHODS ===
    
    public static function create(string $action, string $label): self
    {
        return new self($action, $label);
    }

    public static function custom(string $action, string $label): self
    {
        return new self($action, $label);
    }
    
    public static function view(string $route): self
    {
        return (new self('view', 'View'))
            ->setRoute($route)
            ->setIcon('bi-eye')
            ->setVariant('outline-primary');
    }
    
    public static function edit(string $route): self
    {
        return (new self('edit', 'Edit'))
            ->setRoute($route)
            ->setIcon('bi-pencil')
            ->setVariant('outline-secondary');
    }
    
    public static function delete(string $route): self
    {
        return (new self('delete', 'Delete'))
            ->setRoute($route)
            ->setIcon('bi-trash')
            ->setVariant('outline-danger')
            ->setConfirmMessage('Are you sure you want to delete this item?');
    }
    
    // === CONFIGURATION ===
    
    public function setVariant(string $variant): self
    {
        $this->variant = $variant;
        return $this;
    }
    
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->httpMethod = strtoupper($method);
        return $this;
    }

    public function getMethod(): string
    {
        return $this->httpMethod;
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
    
    public function asBulkAction(): self
    {
        $this->isBulk = true;
        return $this;
    }
    
    // === GETTERS ===
    
    public function getAction(): string
    {
        return $this->action;
    }
    
    public function getLabel(): string
    {
        return $this->label;
    }
    
    public function getVariant(): string
    {
        return $this->variant;
    }
    
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    public function getRoute(): ?array
    {
        return $this->route;
    }
    
    public function getConfirmMessage(): ?string
    {
        return $this->confirmMessage;
    }
    
    public function isBulk(): bool
    {
        return $this->isBulk;
    }
    
    // === SERIALIZATION ===
    
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'label' => $this->label,
            'variant' => $this->variant,
            'icon' => $this->icon,
            'method' => $this->httpMethod,
            'route' => $this->route,
            'routeParams' => $this->routeParams,
            'confirmMessage' => $this->confirmMessage,
            'isBulk' => $this->isBulk,
        ];
    }
}
