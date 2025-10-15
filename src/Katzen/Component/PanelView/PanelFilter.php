<?php

namespace App\Katzen\Component\PanelView;

class PanelFilter
{
    public function __invoke(string $name): void
    {
        echo "Hi, $name!";
    }
}
