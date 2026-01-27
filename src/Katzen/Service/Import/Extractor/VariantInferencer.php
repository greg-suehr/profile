<?php

namespace App\Katzen\Service\Import\Extractor;

/**
 * Variant Inferencer
 * 
 * The core competitive advantage of the import system. Automatically detects
 * size patterns in product names and infers the Item + Variant structure.
 * 
 * Transforms: "Latte Sm, Latte Lg, Latte Rg" 
 * Into: "Latte (Item) + 3 size variants (Small, Large, Regular)"
 * 
 * Instead of creating 3 separate Items, the system intelligently recognizes
 * the pattern and creates the proper catalog structure.
 * 
 * This transforms a typically tedious 20-minute manual mapping process
 * into a 2-minute review-and-confirm experience.
 * 
 * @example
 * ```php
 * $inferencer = new VariantInferencer();
 * 
 * # Single product analysis
 * $result = $inferencer->inferVariant("Ethiopia Rg");
 * # Returns: ['base_name' => 'Ethiopia', 'size' => ['code' => 'Rg', 'name' => 'Regular']]
 * 
 * # Batch analysis for grouping
 * $products = ['Latte Sm', 'Latte Lg', 'Oatmeal Scone', 'Mocha Rg'];
 * $grouped = $inferencer->groupByBaseProduct($products);
 * # Returns: [
 * #   'latte' => ['base_name' => 'Latte', 'variants' => [['name' => 'Latte Sm', 'size' => ...], ...]],
 * #   'oatmeal-scone' => ['base_name' => 'Oatmeal Scone', 'variants' => []], // unsized
 * #   'mocha' => ['base_name' => 'Mocha', 'variants' => [...]],
 * # ]
 * ```
 */
final class VariantInferencer
{
    /**
     * Size patterns mapped to normalized names.
     * Order matters: longer patterns first to avoid partial matches.
     * 
     * Each entry maps the abbreviated form to:
     * - name: Human-readable size name
     * - order: Sort order for display (lower = smaller)
     */
    private const SIZE_PATTERNS = [

        'Extra Small' => ['name' => 'Extra Small', 'order' => 1],
        'Extra Large' => ['name' => 'Extra Large', 'order' => 6],
        'Extra-Small' => ['name' => 'Extra Small', 'order' => 1],
        'Extra-Large' => ['name' => 'Extra Large', 'order' => 6],
        'X-Small'     => ['name' => 'Extra Small', 'order' => 1],
        'X-Large'     => ['name' => 'Extra Large', 'order' => 6],
        
        'Small'   => ['name' => 'Small',   'order' => 2],
        'Medium'  => ['name' => 'Medium',  'order' => 3],
        'Large'   => ['name' => 'Large',   'order' => 4],
        'Regular' => ['name' => 'Regular', 'order' => 3],
        
        'XSm' => ['name' => 'Extra Small', 'order' => 1],
        'XS'  => ['name' => 'Extra Small', 'order' => 1],
        'Sm'  => ['name' => 'Small',       'order' => 2],
        'Md'  => ['name' => 'Medium',      'order' => 3],
        'Med' => ['name' => 'Medium',      'order' => 3],
        'Rg'  => ['name' => 'Regular',     'order' => 3],
        'Reg' => ['name' => 'Regular',     'order' => 3],
        'Lg'  => ['name' => 'Large',       'order' => 4],
        'XL'  => ['name' => 'Extra Large', 'order' => 6],
        'XLg' => ['name' => 'Extra Large', 'order' => 6],
        'XXL' => ['name' => '2X Large',    'order' => 7],
        
        'S' => ['name' => 'Small',   'order' => 2],
        'M' => ['name' => 'Medium',  'order' => 3],
        'L' => ['name' => 'Large',   'order' => 4],
        'R' => ['name' => 'Regular', 'order' => 3],
    ];
    
    /**
     * Patterns that look like sizes but aren't (false positives).
     * These should not trigger size detection.
     */
    private const SIZE_EXCLUSIONS = [
        'wholesale', 'resale', 'original', 'special', 'minimal',
        'normal', 'formal', 'optimal', 'regional', 'seasonal',
        'small plates', 'large format', 'medium roast',
    ];
    
    /**
     * Common delimiters between base name and size.
     */
    private const DELIMITERS = [' - ', ' – ', ' — ', ' | ', ', ', ' '];
    
    /**
     * Infer variant information from a product name.
     * 
     * Attempts to detect if the product name contains a size indicator
     * and extracts the base product name and size information.
     * 
     * @param string $productName The full product name to analyze
     * @return array{
     *   base_name: string,
     *   size: ?array{code: string, name: string, order: int},
     *   original_name: string,
     *   confidence: float
     * }
     */
    public function inferVariant(string $productName): array
    {
        $originalName = $productName;
        $trimmedName = trim($productName);
        
        if ($this->isExcluded($trimmedName)) {
            return $this->noSizeResult($originalName, $trimmedName);
        }
        
        $sizeResult = $this->detectSize($trimmedName);
        
        if ($sizeResult === null) {
            return $this->noSizeResult($originalName, $trimmedName);
        }
        
        return [
            'base_name' => $sizeResult['base_name'],
            'size' => [
                'code' => $sizeResult['code'],
                'name' => $sizeResult['name'],
                'order' => $sizeResult['order'],
            ],
            'original_name' => $originalName,
            'confidence' => $sizeResult['confidence'],
        ];
    }
    
    /**
     * Group a list of product names by their inferred base product.
     * 
     * Analyzes all names to find common base products and groups
     * their variants together. Products without size indicators
     * are treated as standalone items.
     * 
     * @param array<string> $productNames List of product names to group
     * @return array<string, array{
     *   base_name: string,
     *   key: string,
     *   is_sized: bool,
     *   variants: array<array{name: string, size: ?array}>
     * }>
     */
    public function groupByBaseProduct(array $productNames): array
    {
        $groups = [];
        
        foreach ($productNames as $name) {
            $inference = $this->inferVariant($name);
            $baseName = $inference['base_name'];
            $key = $this->normalizeKey($baseName);
            
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'base_name' => $baseName,
                    'key' => $key,
                    'is_sized' => false,
                    'variants' => [],
                ];
            }
            
            if ($inference['size'] !== null) {
                $groups[$key]['is_sized'] = true;
                $groups[$key]['variants'][] = [
                    'name' => $inference['original_name'],
                    'size' => $inference['size'],
                ];
            } else {
                $groups[$key]['variants'][] = [
                    'name' => $inference['original_name'],
                    'size' => null,
                ];
            }
        }
        
        foreach ($groups as &$group) {
            usort($group['variants'], function ($a, $b) {
                $orderA = $a['size']['order'] ?? 999;
                $orderB = $b['size']['order'] ?? 999;
                return $orderA <=> $orderB;
            });
        }
        
        return $groups;
    }
    
    /**
     * Analyze a dataset to find all unique base products and their size patterns.
     * 
     * Returns detailed diagnostics about what was found, useful for
     * displaying to users during import configuration.
     * 
     * @param array<string> $productNames All product names in the dataset
     * @return array{
     *   total_products: int,
     *   unique_bases: int,
     *   sized_products: int,
     *   unsized_products: int,
     *   size_distribution: array<string, int>,
     *   base_products: array<string, array>
     * }
     */
    public function analyzeDataset(array $productNames): array
    {
        $groups = $this->groupByBaseProduct($productNames);
        
        $sizeDistribution = [];
        $sizedCount = 0;
        $unsizedCount = 0;
        
        foreach ($groups as $group) {
            foreach ($group['variants'] as $variant) {
                if ($variant['size'] !== null) {
                    $sizedCount++;
                    $sizeName = $variant['size']['code'];
                    $sizeDistribution[$sizeName] = ($sizeDistribution[$sizeName] ?? 0) + 1;
                } else {
                    $unsizedCount++;
                }
            }
        }
        
        arsort($sizeDistribution);
        
        return [
            'total_products' => count($productNames),
            'unique_bases' => count($groups),
            'sized_products' => $sizedCount,
            'unsized_products' => $unsizedCount,
            'size_distribution' => $sizeDistribution,
            'base_products' => $groups,
        ];
    }
    
    /**
     * Check if a base product name has multiple size variants in the data.
     * 
     * Used to determine if we should create an Item with SellableVariants
     * or just a single Sellable.
     * 
     * @param string $baseName The base product name
     * @param array<string> $allNames All product names in the dataset
     * @return bool True if multiple sizes exist for this base product
     */
    public function hasMultipleSizes(string $baseName, array $allNames): bool
    {
        $baseKey = $this->normalizeKey($baseName);
        $sizes = [];
        
        foreach ($allNames as $name) {
            $inference = $this->inferVariant($name);
            if ($this->normalizeKey($inference['base_name']) === $baseKey && $inference['size'] !== null) {
                $sizes[$inference['size']['code']] = true;
            }
        }
        
        return count($sizes) > 1;
    }
    
    /**
     * Detect size pattern in a product name.
     * 
     * @return ?array{base_name: string, code: string, name: string, order: int, confidence: float}
     */
    private function detectSize(string $name): ?array
    {
        foreach (self::DELIMITERS as $delimiter) {
            $result = $this->detectSizeWithDelimiter($name, $delimiter);
            if ($result !== null) {
                return $result;
            }
        }
        
        return $this->detectSizeSuffix($name);
    }
    
    /**
     * Detect size pattern using a specific delimiter.
     */
    private function detectSizeWithDelimiter(string $name, string $delimiter): ?array
    {
        if (!str_contains($name, $delimiter)) {
            return null;
        }
        
        $parts = explode($delimiter, $name);
        $lastPart = trim(array_pop($parts));
        
        $sizeInfo = $this->matchSizePattern($lastPart);
        if ($sizeInfo === null) {
            return null;
        }
        
        $baseName = implode($delimiter, $parts);
        
        return [
            'base_name' => trim($baseName),
            'code' => $lastPart,
            'name' => $sizeInfo['name'],
            'order' => $sizeInfo['order'],
            'confidence' => 0.9,
        ];
    }
    
    /**
     * Detect size pattern as a suffix (space-separated word at end).
     */
    private function detectSizeSuffix(string $name): ?array
    {
        $words = preg_split('/\s+/', $name);
        if (count($words) < 2) {
            return null;
        }
        
        $lastWord = array_pop($words);
        $sizeInfo = $this->matchSizePattern($lastWord);
        
        if ($sizeInfo === null) {
            return null;
        }
        
        # Limit confidence for single-letter sizes on very short base names
        if (strlen($lastWord) === 1 && count($words) < 2) {
            return null;
        }
        
        $baseName = implode(' ', $words);
        
        $confidence = match (true) {
            strlen($lastWord) >= 5 => 0.95,
            strlen($lastWord) >= 2 => 0.85,
            default => 0.6,                
        };
        
        return [
            'base_name' => trim($baseName),
            'code' => $lastWord,
            'name' => $sizeInfo['name'],
            'order' => $sizeInfo['order'],
            'confidence' => $confidence,
        ];
    }
    
    /**
     * Match a string against size patterns.
     * 
     * @return ?array{name: string, order: int}
     */
    private function matchSizePattern(string $candidate): ?array
    {
        $candidate = trim($candidate);
        
        foreach (self::SIZE_PATTERNS as $pattern => $info) {
            if (strcasecmp($candidate, $pattern) === 0) {
                return $info;
            }
        }
        
        return null;
    }
    
    /**
     * Check if the name should be excluded from size detection.
     */
    private function isExcluded(string $name): bool
    {
        $lower = strtolower($name);
        
        foreach (self::SIZE_EXCLUSIONS as $exclusion) {
            if (str_contains($lower, $exclusion)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build a result for a product with no size detected.
     */
    private function noSizeResult(string $originalName, string $trimmedName): array
    {
        return [
            'base_name' => $trimmedName,
            'size' => null,
            'original_name' => $originalName,
            'confidence' => 1.0,
        ];
    }
    
    /**
     * Normalize a string into a consistent key format.
     */
    private function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key);
        $key = trim($key, '-');
        return $key;
    }
    
    /**
     * Get all recognized size codes for display/documentation.
     * 
     * @return array<string, array{name: string, order: int}>
     */
    public function getSizePatterns(): array
    {
        return self::SIZE_PATTERNS;
    }
    
    /**
     * Extract just the base name from a product name (for quick lookups).
     */
    public function extractBaseName(string $productName): string
    {
        $inference = $this->inferVariant($productName);
        return $inference['base_name'];
    }
    
    /**
     * Extract just the size info from a product name (for quick lookups).
     * 
     * @return ?array{code: string, name: string, order: int}
     */
    public function extractSize(string $productName): ?array
    {
        $inference = $this->inferVariant($productName);
        return $inference['size'];
    }
}
