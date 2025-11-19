<?php

namespace App\Katzen\Component\ShowPage;

class ShowPageFooter
{
  private array $summary = [];
  private array $terminalActions = [];
  
  public static function create(): self
  {
    return new self();
  }

  public function addSummary(string $label, string $value, ?string $class = null): self
  {
    $this->summary[] = [
      'label' => $label,
      'value' => $value,
      'class' => $class,
    ];
    
    return $this;
  }

  public function addTerminalAction(
    string $name,
    string $label,
    ?string $route = null,
    array $routeParams = [],
    string $variant = 'secondary',
    ?string $icon = null,
    ?string $confirmMessage = null,
    bool $disabled = false
  ): self {
    $this->terminalActions[] = [
      'name' => $name,
      'label' => $label,
      'route' => $route,
      'routeParams' => $routeParams,
      'variant' => $variant,
      'icon' => $icon,
      'confirmMessage' => $confirmMessage,
      'disabled' => $disabled,
    ];
    
    return $this;
  }
  
  public function build(): array
  {
    return [
      'summary' => $this->summary,
      'terminalActions' => $this->terminalActions,
    ];
  }
}
