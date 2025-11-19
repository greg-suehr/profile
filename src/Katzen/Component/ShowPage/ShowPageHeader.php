<?php

namespace App\Katzen\Component\ShowPage;

use App\Katzen\Component\ShowPage\PageAction;

class ShowPageHeader
{
  private string $title;
  private string $subtitle;
  private array $statusBadge;
  private array $quickActions = [];
  private array $tabs = [];

  private function __construct()
    {}

  public static function create(): self
  {
    return new self();
  }

  public function setTitle(string $title): self
  {
      $this->title = $title;
      return $this;
  }

  public function setSubtitle(string $subtitle): self
  {
      $this->subtitle = $subtitle;
      return $this;
  }

  public function setStatusBadge(string $label, string $variant)
  {
      $this->statusBadge['label'] = $label;
      $this->statusBadge['variant'] = $variant;
      return $this;
  }

  public function addTab(string $key, string $label, string $icon)
  {
      $this->tabs[] = [
        'key' => $key,
        'label' => $label,
        'icon' => $icon,
        'badge' => null,
      ];

      return $this;
  }

  public function addQuickAction(PageAction $actionConfig)
  {
      $this->quickActions[] = $actionConfig;
      return $this;
  }

  /**
   * Build and return the configuration array for Twig
   */
  public function build(): array
  {
    return [
      'title' => $this->title,
      'subtitle' => $this->subtitle,
      'statusBadge' => $this->statusBadge,# array_map(fn($f) => $f->toArray(), $this->statusBadge),
      'quickActions' => array_map(fn($f) => $f->toArray(), $this->quickActions),
      'tabs' => $this->tabs,
    ];
  }

}

  
  
                               
