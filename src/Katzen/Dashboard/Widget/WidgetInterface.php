<?php

namespace App\Katzen\Dashboard\Widget;

interface WidgetInterface
{
    /** A unique key (used for ordering, cache keys, toggles) */
    public function getKey(): string;

    /** Data for the card renderer */
    public function getViewModel(): WidgetView;
}

?>
