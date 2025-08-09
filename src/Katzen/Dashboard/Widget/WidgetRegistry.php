<?php

namespace App\Katzen\Dashboard\Widget;

final class WidgetRegistry
{
    /** @param iterable<WidgetInterface> $widgets */
    public function __construct(private iterable $widgets) {}

    /** @return WidgetView[] */
    public function all(): array
    {
        $views = [];
        foreach ($this->widgets as $w) {
            $views[] = $w->getViewModel();
        }

        usort($views, fn($a, $b) => $a->key <=> $b->key);
        return $views;
    }
}

?>
