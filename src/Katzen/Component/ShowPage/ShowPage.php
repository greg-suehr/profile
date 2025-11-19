<?php

namespace App\Katzen\Component\ShowPage;

use App\Katzen\Component\ShowPage\ShowPageHeader;
use App\Katzen\Component\ShowPage\ShowPageFooter;
use App\Katzen\Component\ShowPage\PageSection;

/**
 * ShowPage - Builder class for creating Show type pages
 * 
 * Usage:
 *   $page = ShowPage::create('customer')
 *       ->addSection(PageSection::header())
 *       ->addSection(PageSection::header())
 *       ->setRows($menuRows)
 *       ->addQuickAction(PageAction::create('edit, 'Edit'))
 *       ->addTerminalAction(PageAction::create('archive', 'Archive')) 
 *       ->build();
 */
class ShowPage
{
  private string $id;
  private string $title; # TODO
  private ?ShowPageHeader $header = null;
  private array $sections = [];
  private ?ShowPageFooter $footer = null;
  # private array $quickActions = []; # TODO: should Page own QuickActions over Header?
  private array $terminalActions = [];

  private ?string $emptyState = null;
  private array $filters = [];
   
  private function __construct(string $id)
  {
    $this->id = $id;
    $this->emptyState = 'Nothing to see here...';
    $this->searchPlaceholder = 'Search...';
  }
    
  public static function create(string $id): self
  {
    return new self($id);
  }

  public function setHeader(ShowPageHeader $header): self
  {
    $this->header = $header;
    return $this;
  }

  public function setFooter(ShowPageFooter $footer): self
  {
    $this->footer = $footer;
    return $this;
  }
  
  
  public function addSection(PageSection $section): self
  {
    $this->sections[] = $section;
    return $this;
  }
        
  public function addTerminalAction(PageAction $action): self
  {
      $this->terminalActions[] = $action;
      return $this;
  }
    
  public function setEmptyState(string $message): self
  {
      $this->emptyState = $message;
      return $this;
  }
        
  public function addFilter(PageFilter $filter): self
  {
      $this->filters[] = $filter;
      return $this;
  }
    
  /**
   * Build and return the configuration array for Twig
   */
  public function build(): array
  {
    return [
      'id' => $this->id,
      'header' => $this->header ? $this->header->build() : null,
      'sections' => array_map(fn($f) => $f->build(), $this->sections),
      'footer' => $this->footer ? $this->footer->build() : null,
      'terminalActions' => array_map(fn($a) => $a->toArray(), $this->terminalActions),
      'emptyState' => $this->emptyState,
      'filters' => array_map(fn($f) => $f->toArray(), $this->filters),
      'hasTerminalActions' => !empty($this->terminalActions),
    ];
  }
}
