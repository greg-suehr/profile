<?php
namespace App\Katzen\Adapter;

use App\Katzen\Service\Cook\RecipeMappingService;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class JSONAdapter implements AdapterInterface
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

    public function process(UploadedFile $file)
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
