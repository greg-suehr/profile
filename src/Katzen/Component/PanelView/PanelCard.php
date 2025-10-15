<?php

namespace App\Katzen\Component\PanelView;

/**
 * PanelCard - Represents a single card in a PanelView
 * 
 * Cards have:
 * - Header: title, badges, optional meta
 * - Body: default field list or custom template
 * - Footer: quick actions, optional checkbox
 */
class PanelCard
{
    private string $id;
    private string $title;
    private array $badges = [];
    private ?string $meta = null;
    private array $data = [];
    
    // Fields
    private array $primaryFields = [];
    private array $contextFields = [];
    
    // Actions
    private array $quickActions = [];
    
    // Display options
    private ?string $bodyTemplate = null;
    private ?string $styleClass = null;
    private ?string $borderColor = null;
    private ?string $link = null;
    
    private function __construct(string $id)
    {
        $this->id = $id;
    }
    
    public static function create(string $id): self
    {
        return new self($id);
    }
    
    // === CONFIGURATION ===
    
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    public function addBadge(string $text, string $variant = 'secondary'): self
    {
        $this->badges[] = [
            'text' => $text,
            'variant' => $variant,
        ];
        return $this;
    }
    
    public function setMeta(string $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
    
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }
    
    // === FIELDS ===
    
    public function addPrimaryField(PanelField $field): self
    {
        $this->primaryFields[] = $field;
        return $this;
    }
    
    public function addContextField(PanelField $field): self
    {
        // Auto-mute context fields
        $field->muted(true);
        $this->contextFields[] = $field;
        return $this;
    }
    
    // === ACTIONS ===
    
    public function addQuickAction(PanelAction $action): self
    {
        $this->quickActions[] = $action;
        return $this;
    }
    
    // === DISPLAY OPTIONS ===
    
    public function setBodyTemplate(string $template): self
    {
        $this->bodyTemplate = $template;
        return $this;
    }
    
    public function setStyleClass(string $class): self
    {
        $this->styleClass = $class;
        return $this;
    }
    
    public function setBorderColor(string $color): self
    {
        $this->borderColor = $color;
        return $this;
    }
    
    public function setLink(string $route, array $params = []): self
    {
        $this->link = ['route' => $route, 'params' => $params];
        return $this;
    }
    
    // === GETTERS ===
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getTitle(): string
    {
        return $this->title ?? '';
    }
    
    public function getBadges(): array
    {
        return $this->badges;
    }
    
    public function getMeta(): ?string
    {
        return $this->meta;
    }
    
    public function getData(): array
    {
        return $this->data;
    }
    
    public function getPrimaryFields(): array
    {
        return $this->primaryFields;
    }
    
    public function getContextFields(): array
    {
        return $this->contextFields;
    }
    
    public function getQuickActions(): array
    {
        return $this->quickActions;
    }
    
    public function getBodyTemplate(): ?string
    {
        return $this->bodyTemplate;
    }
    
    public function getStyleClass(): ?string
    {
        return $this->styleClass;
    }
    
    public function getBorderColor(): ?string
    {
        return $this->borderColor;
    }
    
    public function getLink(): ?array
    {
        return $this->link;
    }
    
    /**
     * Get searchable text for client-side filtering
     */
    public function getSearchableText(): string
    {
        $parts = [$this->getTitle()];
        
        // Add badge text
        foreach ($this->badges as $badge) {
            $parts[] = $badge['text'];
        }
        
        // Add field values
        foreach ($this->primaryFields as $field) {
            $value = $field->getValue($this->data);
            if ($value !== null) {
                $parts[] = (string) $value;
            }
        }
        
        foreach ($this->contextFields as $field) {
            $value = $field->getValue($this->data);
            if ($value !== null) {
                $parts[] = (string) $value;
            }
        }
        
        return implode(' ', $parts);
    }
    
    // === SERIALIZATION ===
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'badges' => $this->badges,
            'meta' => $this->meta,
            'data' => $this->data,
            'primaryFields' => array_map(fn($f) => $f->toArray(), $this->primaryFields),
            'contextFields' => array_map(fn($f) => $f->toArray(), $this->contextFields),
            'quickActions' => array_map(fn($a) => $a->toArray(), $this->quickActions),
            'bodyTemplate' => $this->bodyTemplate,
            'styleClass' => $this->styleClass,
            'borderColor' => $this->borderColor,
            'link' => $this->link,
            'searchable' => $this->getSearchableText(),
        ];
    }
}
