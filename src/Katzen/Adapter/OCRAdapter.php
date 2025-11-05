<?php

namespace App\Katzen\Adapter;

use App\Katzen\Service\Import\VendorIdentificationService;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * OCR Adapter - sends files to Python OCR microservice
 * Handles images and PDFs
 */
final class OCRAdapter implements AdapterInterface
{
    private const SERVICE_URL = 'http://localhost:5005/ocr/receipt';
    private const TIMEOUT = 30; // 30 seconds for OCR processing
    private const MAX_RETRIES = 2;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private VendorIdentificationService $vendorId,
        private string $ocrServiceUrl = self::SERVICE_URL
    ) {}

    public function supports(string $type): bool
    {
        return in_array($type, ['image', 'pdf'], true);
    }

    /**
     * Process uploaded file through OCR service
     * 
     * @return array Normalized OCR data structure
     * @throws \RuntimeException If OCR service fails
     */
    public function process(UploadedFile $file): array
    {
        $this->validateFile($file);

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = $this->sendToOCRService($file);

                $ocrData = $response->toArray();

                $this->logger->info('OCR processing successful', [
                    'filename' => $file->getClientOriginalName(),
                    'avg_confidence' => $ocrData['avg_conf'] ?? 0,
                    'pages' => count($ocrData['pages'] ?? []),
                ]);

                return $this->normalizeForKatzen($ocrData);

            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $attempt++;
                
                $this->logger->warning('OCR service request failed, retrying', [
                    'attempt' => $attempt,
                    'max_retries' => self::MAX_RETRIES,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    // Exponential backoff: 1s, 2s
                    sleep(pow(2, $attempt - 1));
                }
            } catch (\Throwable $e) {
                $this->logger->error('OCR processing failed', [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \RuntimeException(
                    'Failed to process file: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // All retries exhausted
        throw new \RuntimeException(
            'OCR service unavailable after ' . self::MAX_RETRIES . ' attempts',
            0,
            $lastException
        );
    }

    /**
     * Send file to OCR microservice
     */
    private function sendToOCRService(UploadedFile $file): ResponseInterface
    {
        # $fileStream = fopen($file->getPathname(), 'r');        
        #if (!$fileStream) {
        #    throw new \RuntimeException('Failed to open uploaded file');
        #}

        try {
          $formFields = [
            'file' => DataPart::fromPath(
              $file->getPathname(),
              $file->getClientOriginalName(),
            )
              ];

          $formData = new FormDataPart($formFields);
          
          return $this->httpClient->request('POST', $this->ocrServiceUrl, [
            'timeout' => self::TIMEOUT,
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body'    => $formData->bodyToString(),
          ]);
        } finally {          
          # TODO: catch
        }
    }

    /**
     * Validate file before processing
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid file upload');
        }

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/heic',
            'image/heif',
            'application/pdf',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new \RuntimeException(
                'Unsupported file type: ' . $file->getMimeType()
            );
        }

        // 10MB limit
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \RuntimeException('File too large (max 10MB)');
        }
    }

    /**
     * Transform OCR service response into Katzen's expected format
     */
    private function normalizeForKatzen(array $ocr): array
    {
        $pages = $ocr['pages'] ?? [];
        $avgConf = $ocr['avg_conf'] ?? 0;      
        
        if (empty($pages)) {
            throw new \RuntimeException('No text detected in image');
        }

        // For receipts, we typically only care about the first page
        $firstPage = $pages[0];
        $allText = $this->extractAllText($firstPage);

        return [
            'vendor_guess' => $this->guessVendor($firstPage),
            'invoice_number' => $this->extractInvoiceNumber($allText),
            'invoice_date' => $this->extractDate($allText),
            'subtotal' => $this->extractTotal($allText, 'subtotal'),
            'tax' => $this->extractTotal($allText, 'tax'),
            'total' => $this->extractTotal($allText, 'total'),
            'currency' => 'USD', // Default, could be configurable
            'line_items' => $this->extractLineItems($firstPage),
            'confidence_report' => [
                'avg' => $avgConf,
                'low_confidence_fields' => $this->identifyLowConfidenceFields($firstPage),
            ],
            'raw_ocr_data' => $ocr, // Keep original for debugging
        ];
    }

    /**
     * Extract all text from a page
     */
    private function extractAllText(array $page): string
    {
        $lines = $page['lines'] ?? [];
        return implode("\n", array_column($lines, 'text'));
    }

    /**
     * Basic vendor identification from text
     * This should be replaced with VendorIdentificationService
     */
    private function guessVendor(array $page): ?array
    {
      return $this->vendorId->identify($page['lines']);
    }

    /**
     * Extract invoice number using patterns
     */
    private function extractInvoiceNumber(string $text): ?string
    {
        // Common patterns: INV-12345, #12345, Invoice: 12345
        $patterns = [
            '/INV[:\-\s]*(\d{5,})/i',
            '/INVOICE[:\-\s#]*(\d{5,})/i',
            '/#\s*(\d{6,})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract date from text
     */
    private function extractDate(string $text): ?string
    {
        // Patterns: MM/DD/YYYY, YYYY-MM-DD, etc.
        $patterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})/',
            '/(\d{4}\-\d{2}\-\d{2})/',
            '/(\d{1,2}\-\d{1,2}\-\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                try {
                    $date = new \DateTime($matches[1]);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract monetary totals
     */
    private function extractTotal(string $text, string $type): ?float
    {
        $labels = [
            'subtotal' => ['SUBTOTAL', 'SUB TOTAL', 'SUB-TOTAL'],
            'tax' => ['TAX', 'SALES TAX', 'GST'],
            'total' => ['TOTAL', 'AMOUNT DUE', 'BALANCE DUE'],
        ];

        foreach ($labels[$type] ?? [] as $label) {
            // Pattern: LABEL $12.34 or LABEL 12.34
            $pattern = '/' . preg_quote($label, '/') . '\s*\$?\s*(\d+\.\d{2})/i';
            if (preg_match($pattern, $text, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    private function normalizeLine(string $s): string
    {
      	$s = preg_replace('/[[:cntrl:]]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /**
     * Extract line items from structured OCR data
     */
    private function extractLineItems(array $page): array
    {
        # TOOD: move logic to Import/ItemIdentificationService
        $lines = $page['lines'] ?? [];
        $items = [];

        // Common token patterns
        $PRICE     = '(?<price>\d+\.\d{2})';
        $UNIT      = '(?<unit_price>\d+\.\d{2})';
        $QTY       = '(?<qty>\d+(?:\.\d{1,3})?)';
        $UPC       = '(?<upc>\b\d{12,14}\b|\b\d{8}\b)'; // prefer 12–14, also allow EAN-8
        $FLAGS_END = '(?<flags>(?:\s+[A-Z]){0,3})?';    // trailing single-letter flags, up to 3
        
        // Compile a few focused patterns
        $patterns = [
          // 1) Name UPC FLAG(S) PRICE FLAG(S)
          //    e.g., "BABY CARROTS 003338366602 I 1.48 N"
          '/^(?<name>.+?)\s+' . $UPC . '\s+(?<midflags>(?:[A-Z]\s+)*)' . $PRICE . '\s*' . $FLAGS_END . '$/i',
          
          // 2) Weighted items: "APPLES 1.23 LB @ 1.99/LB 2.45"
          '/^(?<name>.+?)\s+' . $QTY . '\s*(?<uom>LB|KG)\s*@\s*\$?' . $UNIT . '\s*\/\s*(?:LB|KG)\s+' . $PRICE . '\s*' . $FLAGS_END . '$/i',
          
          // 3) Qty @ unit = ext (ext at end): "BANANAS 2 @ 0.59 1.18"
          '/^(?<name>.+?)\s+' . $QTY . '\s*@\s*\$?' . $UNIT . '\s+' . $PRICE . '\s*' . $FLAGS_END . '$/i',
              
          // 4) Name … PRICE … FLAGS (allow UPC anywhere before price)
          '/^(?<name>.+?)\s+(?:.*?\b(?<upc>\d{12,14}|\d{8})\b.*?\s+)?' . $PRICE . '\s*' . $FLAGS_END . '$/i',
        ];        

        foreach ($lines as $line) {
          $text = $this->normalizeLine($line['text']);
          $avgConf = (float)($line['avg_conf'] ?? 0.0);
           
            // Skip header/footer lines (customize based on your receipts)
            // if ($this->isHeaderOrFooter($text)) {
            //    continue;
            //}

          if ($text === '') {
            continue;
          }

          $matched = false;

          foreach ($patterns as $pi => $re) {
            if (preg_match($re, $text, $m)) {
              $matched = true;

              $name = trim($m['name'] ?? '');
              // If name still contains an embedded UPC from pattern 4, strip it from the name portion
              if (!empty($m['upc'])) {
                $name = preg_replace('/\b' . preg_quote($m['upc'], '/') . '\b/', '', $name);
                $name = trim(preg_replace('/\s+/', ' ', $name));
              }
              
              // Flags: merge midflags + end flags if present
              $flags = [];
              foreach (['midflags','flags'] as $flagKey) {
                if (!empty($m[$flagKey])) {
                  preg_match_all('/\b([A-Z])\b/', $m[$flagKey], $ff);
                  if (!empty($ff[1])) { $flags = array_merge($flags, $ff[1]); }
                }
              }
              $flags = array_values(array_unique($flags));

              $item = [
                'raw_text'   => $text,
                'name'       => $name !== '' ? $name : null,
                'ext_price'  => isset($m['price']) ? (float)$m['price'] : null,
                'unit_price' => isset($m['unit_price']) ? (float)$m['unit_price'] : null,
                'qty'        => isset($m['qty']) ? (float)$m['qty'] : 1.0,
                'uom'        => isset($m['uom']) ? strtoupper($m['uom']) : null,
                'upc'        => $m['upc'] ?? null,
                'flags'      => $flags,
                'match_type' => 'pattern_' . ($pi + 1),
              ];

              // Confidence: OCR conf + pattern bonus
              //   - strong bonus for specific patterns, small for generic
              $patternBonus = [0.20, 0.18, 0.15, 0.10][$pi] ?? 0.05;
              $item['confidence'] = max(0.0, min(1.0, $avgConf + $patternBonus));

              $items[] = $item;
              break;
            }
          }

          if ($matched) {
            continue;
          }

          // Fallback heuristic:
          //  - ext_price := last X.YY token
          //  - upc := longest 12–14 digit run, else 8
          //  - name := text before price (or entire line) with UPC removed
          $priceTokens = [];
          if (preg_match_all('/\b(\d+\.\d{2})\b/', $text, $pp)) {
            $priceTokens = $pp[1];
          }
          
          $upcCandidate = null;
          if (preg_match_all('/\b\d{12,14}\b/', $text, $uu) && !empty($uu[0])) {
            // choose the leftmost 12–14 digit run
            $upcCandidate = $uu[0][0];
          } elseif (preg_match('/\b\d{8}\b/', $text, $u8)) {
            $upcCandidate = $u8[0];
          }

          $extPrice = null;
          $name = $text;
          if (!empty($priceTokens)) {
            $extPrice = (float)end($priceTokens);
            // take everything before the last price token occurrence as name
            $lastPos = strrpos($text, (string)end($priceTokens));
            if ($lastPos !== false) {
              $name = trim(substr($text, 0, $lastPos));
            }
          }
          
          if ($upcCandidate) {
            $name = preg_replace('/\b' . preg_quote($upcCandidate, '/') . '\b/', '', $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));
          }

          if ($extPrice !== null || $upcCandidate !== null) {
            // pull any trailing single-letter flags at end
            $flags = [];
            if (preg_match('/(?:\b[A-Z]\b\s*){1,3}$/', $text, $ff)) {
                preg_match_all('/\b([A-Z])\b/', $ff[0], $f2);
                if (!empty($f2[1])) { $flags = $f2[1]; }
            }

            $items[] = [
                'raw_text'   => $text,
                'name'       => $name !== '' ? $name : null,
                'ext_price'  => $extPrice,
                'unit_price' => null,
                'qty'        => 1.0,
                'uom'        => null,
                'upc'        => $upcCandidate,
                'flags'      => $flags,
                'match_type' => 'fallback',
                // bonus for any detectable anchor
                'confidence' => max(0.0, min(1.0, $avgConf + ($extPrice !== null && $upcCandidate !== null ? 0.12 : 0.06))),
            ];
          }
        }
        
        
        return $items;
    }

    /**
     * Identify if a line is likely header/footer
     */
    private function isHeaderOrFooter(string $text): bool
    {
        $headerFooterKeywords = [
          'THANK YOU',
          'RECEIPT',
          'TAX',
          'TOTAL',
          'SUBTOTAL',
          'CASH',
          'CREDIT',
          'DEBIT',
        ];

        $textUpper = strtoupper($text);
        foreach ($headerFooterKeywords as $keyword) {
            if (strpos($textUpper, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identify fields with low OCR confidence
     */
    private function identifyLowConfidenceFields(array $page): array
    {
        $lowConf = [];
        $threshold = 75;

        $words = $page['words'] ?? [];
        foreach ($words as $word) {
            if (($word['conf'] ?? 100) < $threshold) {
                $lowConf[] = [
                    'text' => $word['text'],
                    'confidence' => $word['conf'],
                ];
            }
        }

        return $lowConf;
    }
}
