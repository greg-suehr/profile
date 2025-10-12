<?php
namespace App\Katzen\Adapter;

use App\Katzen\Service\Cook\RecipeMappingService;

class CSVAdapter implements RecipeAdapterInterface
{
    public function supports(string $type): bool
    {
        return $type === 'csv';
    }

    public function process(mixed $file)
    {
        // Parse CSV and return structured recipe data
        return "Processing CSV file: " . $file->getClientOriginalName();
    }
}
