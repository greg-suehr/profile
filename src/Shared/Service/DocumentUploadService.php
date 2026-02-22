<?php

namespace App\Shared\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentUploadService
{
  public function __construct(
    private readonly string $uploadDirectory,
    private readonly SluggerInterface $slugger,
  ) {}

  public function upload(UploadedFile $file): string
  {
    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeBaseName = $this->slugger->slug($originalName)->lower();
    $uniqueSuffix = substr(bin2hex(random_bytes(3)), 0, 6);
    $newFilename  = $safeBaseName . '-' . $uniqueSuffix . '.' . $file->guessExtension();
    
    $file->move($this->uploadDirectory, $newFilename);
    
    return $newFilename;
  }

  public function delete(string $filename): void
  {
    $fullPath = $this->uploadDirectory . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($fullPath)) {
      unlink($fullPath);
    }
  }

  public function getUploadDirectory(): string
  {
    return $this->uploadDirectory;
  }
}
