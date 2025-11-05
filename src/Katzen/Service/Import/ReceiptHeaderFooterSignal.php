<?php

namespace App\Katzen\Service\Import;

final class ReceiptHeaderFooterSignal
{
    public function __construct(
        public string $type,            // 'phone','url','address','total','subtotal','store_number','tax_id','email','brand_token'
        public array $data,             // normalized payload (e.g. ['e164' => '14125551234'])
        public string $raw,             // original line
        public float $confidence = 0.6, // per-signal confidence
    ) {}
}
