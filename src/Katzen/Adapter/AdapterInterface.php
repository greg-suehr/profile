<?php
namespace App\Katzen\Adapter;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

interface AdapterInterface
{
    public function supports(string $type): bool;
    public function process(UploadedFile $file);
}
