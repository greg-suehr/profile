<?php
namespace App\Service;

use App\Adapter\RecipeAdapterInterface;
use App\Adapter\JSONAdapter;
use App\Adapter\CSVAdapter;
use App\Adapter\PDFAdapter;
use App\Adapter\OCRAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RecipeImportService
{
    private EntityManagerInterface $entityManager;
    private iterable $adapters;
    private array $adapterMap = [];

    public function __construct(EntityManagerInterface $entityManager, iterable $adapters)
    {
      $this->entityManager = $entityManager;
      $this->adapters = $adapters; // An iterable collection of all adapters

      foreach ($this->adapters as $adapter) {
        if ($adapter instanceof RecipeAdapterInterface) {
          if ($adapter->supports('json')) {
            $this->adapterMap['json'] = $adapter;
          }
          if ($adapter->supports('csv')) {
            $this->adapterMap['csv'] = $adapter;
          }
          if ($adapter->supports('pdf')) {
            $this->adapterMap['pdf'] = $adapter;
          }
          if ($adapter->supports('image')) {
            $this->adapterMap['image'] = $adapter;
          }
        }
      }
    }

    public function importFile(UploadedFile $file)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!isset($this->adapterMap[$extension])) {
          throw new \Exception("Unsupported file type: $extension");
        }

        return $this->adapterMap[$extension]->process($file);
    }

    public function importData(array $data)
    {
        if (!isset($this->adapterMap['json'])) {
          throw new \Exception("JSON Adapter not found");
        }

        return $this->adapterMap['json']->process($data);
    }
}

?>
