<?php
namespace App\Adapter;

use App\Service\RecipeMappingService;

class JSONAdapter implements RecipeAdapterInterface
{
    private RecipeMappingService $mappingService;

    public function __construct(RecipeMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    public function supports(string $type): bool
    {
        return $type === 'json';
    }

    public function process(mixed $file)
    {
        if (is_array($file)) {
            return $this->mappingService->mapAndStore($file);
        }

        $data = json_decode(file_get_contents($file->getPathname()), true);

        if (!isset($data['title'], $data['ingredients'], $data['instructions'])) {
            throw new \Exception("Invalid JSON format");
        }

        return $this->mappingService->mapAndStore($data);
    }
}
