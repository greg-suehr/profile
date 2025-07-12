<?php
namespace App\Katzen\Adapter;

class OCRAdapter implements RecipeAdapterInterface
{
    public function supports(string $type): bool
    {
        return $type === 'image';
    }

    public function process(mixed $file)
    {
        return "Processing image file: " . $file->getClientOriginalName();
    }
}
