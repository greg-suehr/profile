<?php

namespace App\Katzen\Component\ShowPage;

class PageSection
{
  private string $type;
  private ?string $label = null;
  private ?string $tab = null;
  private ?string $class = null;
  private ?string $id = null;
  
  private array $items = [];
  private int $columns = 2;
  private int $width = 12;
    
  private mixed $tableView = null;
  private ?string $content = null;
  
  private array $tabs = [];

  private function __construct(string $type)
  {
    $this->type = $type;
  }

  public static function createInfoBox(?string $label = null): self
  {
    $section = new self('infobox');
    if ($label) {
      $section->setLabel($label);
    }
    return $section;
  }

  public static function createTable(?string $label = null): self
  {
    $section = new self('table');
    if ($label) {
      $section->setLabel($label);
    }
    return $section;
  }

  public static function createTabs(?string $label = null, ?string $id = null): self
  {
    $section = new self('tabs');
    if ($label) {
      $section->setLabel($label);
    }
    if ($id) {
      $section->id = $id;
    } else {
      $section->id = 'tabs-' . uniqid();
    }
    return $section;
  }

  public static function createHtml(?string $label = null): self
  {
    $section = new self('html');
    if ($label) {
      $section->setLabel($label);
    }
    return $section;
  }

  public function setLabel(string $label): self
  {
    $this->label = $label;
    return $this;
  }

  public function setTab(string $tab): self
  {
    $this->tab = $tab;
    return $this;
  }

  public function setClass(string $class): self
  {
    $this->class = $class;
    return $this;
  }

  public function setWidth(int $width): self
  {
    $this->width = $width;
    return $this;
  }

  ##########
  # INFOBOX
  ##########  
    
  public function setColumns(int $columns): self
  {
    $this->columns = $columns;
    return $this;
  }

  public function addItem(
    string $label,
    mixed $value,
    string $type = 'text',
    ?string $route = null,
    array $routeParams = [],
    ?string $variant = null,
    ?string $format = null,
    ?string $class = null
  ): self {
    $this->items[] = [
      'label' => $label,
      'value' => $value,
      'type' => $type,
      'route' => $route,
      'routeParams' => $routeParams,
      'variant' => $variant,
      'format' => $format,
      'class' => $class,
    ];
    
    return $this;
  }


  #####################
  # TableView Component
  #####################
  
  public function setTableView(mixed $tableView): self
  {
    $this->tableView = $tableView;
    return $this;
  }

  ###########
  # Just HTML, extremely classy
  ###########
  
  public function setContent(string $content): self
  {
    $this->content = $content;
    return $this;
  }

  ###################
  # Tabs arent tables
  ###################
    
  public function addTab(string $key, string $label, ?self $section = null, ?string $icon = null): self
  {
    $this->tabs[] = [
      'key' => $key,
      'label' => $label,
      'section' => $section?->build(),
      'icon' => $icon,
    ];
        
    return $this;
  }

  public function build(): array
  {
    $base = [
      'type' => $this->type,
      'label' => $this->label,
      'tab' => $this->tab,
      'class' => $this->class,
      'width' => $this->width,
    ];

    return match($this->type) {
      'infobox' => array_merge($base, [
        'items' => $this->items,
        'columns' => $this->columns,
      ]),
      'table' => array_merge($base, [
        'tableView' => $this->tableView,
        'content' => $this->content,
      ]),
      'tabs' => array_merge($base, [
        'id' => $this->id,
        'tabs' => $this->tabs,
      ]),
      'html' => array_merge($base, [
        'content' => $this->content,
      ]),
      default => $base,
    };
  }
}
