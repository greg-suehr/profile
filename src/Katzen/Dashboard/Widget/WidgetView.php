<?php

namespace App\Katzen\Dashboard\Widget;

final class WidgetView
{
    public function __construct(
        public string $key,
        public string $title,
        public string $value,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public ?string $tone = null, # 'success' | 'warning' | 'error'
    ) {}
    
    public function toArray(): array
    {
        return [
            'key'      => $this->key,
            'title'    => $this->title,
            'value'    => $this->value,
            'subtitle' => $this->subtitle,
            'icon'     => $this->icon,
            'tone'     => $this->tone,
        ];
    }
}

?>
