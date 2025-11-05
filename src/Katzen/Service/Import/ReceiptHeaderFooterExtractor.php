<?php

namespace App\Katzen\Service\Import;

/**
 * This "class" is a collection of regular expressions, which have been carefully
 * curated from a variety of StackExchange posts, "large language models" and a
 * good deal of random, desperate flailing on an unergonomic keyboard.
 *
 * They are presented here for your convenience with the hope they are rarely
 * if ever read or modified.
 */
final class ReceiptHeaderFooterExtractor
{
    private function normPhone(string $s): ?string {
        $digits = preg_replace('/\D+/', '', $s);
        if (strlen($digits) >= 10) {
            return $digits;
        }
        return null;
    }

    private function normDomain(string $s): ?string {
        $s = strtolower(trim($s));
        if (!str_starts_with($s, 'http')) $s = 'http://' . $s;
        $host = parse_url($s, PHP_URL_HOST);
        if (!$host) return null;

        return preg_replace('/^www\./', '', $host);
    }

    private function normAddressHash(string $s): ?array {
        $postal = null;

        # USA 55555(+4444) ZIP code pattern                                                                                                              
        if (preg_match('/\b(\d{5})(?:\-\d{4})?\b/', $s, $matches)) {
          $postal = $matches[1];
        }
        # CAD (A1A 1A1) postal code pattern                                                                                                              
        elseif (preg_match('/\b([A-Z]\d[A-Z]\s?\d[A-Z]\d)\b/i', $s, $matches)) {
          $postal = strtoupper(str_replace(' ', '', $matches[1]));
        }
        
        $normalized = strtolower($s);
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        # TODO: move logic duped in Vendor entity to some AddressNormalizer service
        $replacements = [
          ' street' => ' st',
          ' avenue' => ' ave',
          ' road' => ' rd',
          ' drive' => ' dr',
          ' boulevard' => ' blvd',
        ];
        
        $normalized = str_replace(array_keys($replacements), array_values($replacements), $normalized);        

        return ['hash' => hash('sha256', $normalized), 'postal' => $postal];
    }

    /** @return ReceiptHeaderFooterSignal[] */
    public function extract(string $line): array
    {
        $signals = [];
        $raw = trim($line);
        $u = strtoupper($raw);

        if (preg_match('/\bSUBTOTAL\b|\bTOTAL\b|\bTAX\b/', $u)) {
            $signals[] = new ReceiptHeaderFooterSignal('totalish', [], $raw, 0.5);
        }

        if (preg_match('/(\+?\d[\d\-\.\s\(\)]{8,}\d)/', $raw, $m)) {
            if ($p = $this->normPhone($m[1])) {
                $signals[] = new ReceiptHeaderFooterSignal('phone', ['e164ish' => $p], $raw, 0.9);
            }
        }

        if (preg_match('/\b((?:https?:\/\/)?[a-z0-9\-\.]+\.[a-z]{2,}(?:\/\S*)?)\b/i', $raw, $m)) {
            if ($d = $this->normDomain($m[1])) {
                $signals[] = new ReceiptHeaderFooterSignal('url', ['domain' => $d], $raw, 0.85);
            }
        }

        if (preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $raw, $m)) {
            $signals[] = new ReceiptHeaderFooterSignal('email', ['email' => strtolower($m[0])], $raw, 0.8);
        }

        if (preg_match('/\b(VAT|GST|TAX\s*ID|ABN|TIN)[:\s\-]*([A-Z0-9\-]+)\b/i', $raw, $m)) {
            $signals[] = new ReceiptHeaderFooterSignal('tax_id', ['label' => strtoupper($m[1]), 'value' => strtoupper($m[2])], $raw, 0.8);
        }

        if (preg_match('/\b(STORE|STR)\s*#?\s*([A-Z0-9\-]+)\b/i', $raw, $m)) {
            $signals[] = new ReceiptHeaderFooterSignal('store_number', ['store_number' => strtoupper($m[2])], $raw, 0.7);
        }
        
        if (preg_match('/\b(AVE|AVENUE|ST|STREET|RD|ROAD|BLVD|LANE|LN|DR|DRIVE)\b/i', $raw)) {
            if ($addr = $this->normAddressHash($raw)) {
                $signals[] = new ReceiptHeaderFooterSignal('address', $addr, $raw, 0.7);
            }
        }
        if (preg_match('/\b(KROGER|WALMART|TARGET|ALDI|WHOLE\s*FOODS|COSTCO|SAFEWAY|GIANT|PUBLIX|TRADER\s*JOE\'?S)\b/i', $raw, $m)) {
            $signals[] = new ReceiptHeaderFooterSignal('brand_token', ['token' => strtoupper($m[1])], $raw, 0.6);
        }

        return $signals;
    }
}
