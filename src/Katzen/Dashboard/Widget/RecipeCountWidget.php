<?php

namespace App\Katzen\Dashboard\Widget;

use App\Katzen\Repository\RecipeRepository;

final class RecipeCountWidget implements WidgetInterface
{
    public function __construct(private RecipeRepository $recipes) {}

    public function getKey(): string { return 'kpi.recipes.total'; }

    public function getViewModel(): WidgetView
    {
        $total = $this->recipes->count([]);
        return new WidgetView(
            key: $this->getKey(),
            title: 'Total Recipes',
            value: (string)$total,
        );
    }
}

?>
