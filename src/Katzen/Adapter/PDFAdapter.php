<?php
namespace App\Katzen\Adapter;

use App\Katzen\Service\Cook\RecipeMappingService;
use Smalot\PdfParser\Parser;

class PDFAdapter implements RecipeAdapterInterface
{
    private RecipeMappingService $mappingService;

    public function __construct(RecipeMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

  // TODO: implement PDFAdapter
    public function supports(string $type): bool
    {        
        return $type === 'pdf';
    }
  
    public function process($file)
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($file->getPathname());
        $text = $pdf->getText();

        // Convert raw text to structured recipe format (requires NLP or rule-based parsing)
        $structuredData = $this->parseRecipeFromText($text);

        return $this->mappingService->mapAndStore($structuredData);
    }

    private function parseRecipeFromText($text)
    {
        // Basic rule-based text extraction (can be improved with NLP)
        $lines = explode("\n", $text);
        $title = trim($lines[0]);

        return [
            "title" => $title,
            "summary" => "",
            "ingredients" => [],
            "instructions" => []
        ];
    }
}
?>
