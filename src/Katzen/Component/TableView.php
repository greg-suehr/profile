<?php

namespace App\Katzen\Component;

/**
 * TableView - Builder class for creating table views
 * 
 * Usage:
 *   $table = TableView::create('menus')
 *       ->addField(TableField::text('name', 'Name')->sortable())
 *       ->addField(TableField::badge('status', 'Status'))
 *       ->setRows($menuRows)
 *       ->setSelectable(true)
 *       ->addBulkAction(TableAction::create('archive', 'Archive Selected'))
 *       ->build();
 */
class TableView
{
  private string $id;
  private array $fields = [];
  private array $rows = [];
  private array $quickActions = [];
  private array $bulkActions = [];
  private bool $selectable = false;
  private bool $showToolbar = true;
  private bool $stickyHeader = false;
  private ?string $emptyState = null;
  private array $filters = [];
  private int $serverSideThreshold = 100; // filter server-side when dealing with >= rows
  private ?string $searchPlaceholder = null;
  
  private function __construct(string $id)
  {
    $this->id = $id;
    $this->emptyState = 'No items found.';
    $this->searchPlaceholder = 'Search...';
  }
    
  public static function create(string $id): self
  {
    return new self($id);
  }
    
  public function addField(TableField $field): self
  {
    $this->fields[] = $field;
    return $this;
  }
    
  public function setRows(array $rows): self
  {
    $this->rows = array_map(function($row) {
        return $row instanceof TableRow ? $row : TableRow::fromArray($row);
    }, $rows);
    return $this;
  }
    
  public function addQuickAction(TableAction $action): self
  {
      $this->quickActions[] = $action;
      return $this;
  }
    
  public function addBulkAction(TableAction $action): self
  {
      $this->bulkActions[] = $action;
      return $this;
  }
    
  public function setSelectable(bool $selectable): self
  {
      $this->selectable = $selectable;
      return $this;
  }
    
  public function setShowToolbar(bool $show): self
  {
      $this->showToolbar = $show;
      return $this;
  }
    
  public function setStickyHeader(bool $sticky): self
  {
      $this->stickyHeader = $sticky;
      return $this;
  }
    
  public function setEmptyState(string $message): self
  {
      $this->emptyState = $message;
      return $this;
  }
    
  public function setSearchPlaceholder(string $placeholder): self
  {
      $this->searchPlaceholder = $placeholder;
      return $this;
  }
    
  public function setServerSideThreshold(int $threshold): self
  {
      $this->serverSideThreshold = $threshold;
      return $this;
  }
    
  public function addFilter(TableFilter $filter): self
  {
      $this->filters[] = $filter;
      return $this;
  }
    
  /**
   * Build and return the configuration array for Twig
   */
  public function build(): array
  {
    $rowCount = count($this->rows);
    $useServerSide = $rowCount > $this->serverSideThreshold;
    
    return [
      'id' => $this->id,
      'fields' => array_map(fn($f) => $f->toArray(), $this->fields),
      'rows' => array_map(fn($r) => $r->toArray(), $this->rows),
      'quickActions' => array_map(fn($a) => $a->toArray(), $this->quickActions),
      'bulkActions' => array_map(fn($a) => $a->toArray(), $this->bulkActions),
      'selectable' => $this->selectable,
      'showToolbar' => $this->showToolbar,
      'stickyHeader' => $this->stickyHeader,
      'emptyState' => $this->emptyState,
      'searchPlaceholder' => $this->searchPlaceholder,
      'filters' => array_map(fn($f) => $f->toArray(), $this->filters),
      'rowCount' => $rowCount,
      'useServerSide' => $useServerSide,
      'hasQuickActions' => !empty($this->quickActions),
      'hasBulkActions' => !empty($this->bulkActions),
    ];
  }
}
