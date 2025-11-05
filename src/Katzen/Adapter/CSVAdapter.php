<?php
namespace App\Katzen\Adapter;

use App\Katzen\Service\Cook\RecipeMappingService;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class CSVAdapter implements AdapterInterface
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
