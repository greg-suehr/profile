<?php

namespace App\Katzen\Service\Import\Extractor;

use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\SellableComponent;
use App\Katzen\Entity\SellableVariant;
use App\Katzen\Repository\SellableRepository;
use App\Katzen\Repository\SellableVariantRepository;
use App\Katzen\Service\Import\ExtractionResult;
use App\Katzen\Service\Import\Extractor\VariantInferencer;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Catalog Extractor
 * 
 * The flagship extractor that demonstrates the system's intelligent automation.
 * Extracts Sellable, SellableVariant, and SellableComponent entities from 
 * product/sales data with automatic structure inference.
 * 
 * The key innovation: Users don't need to understand our data model or prepare
 * complex spreadsheets. They just import their POS export and we intelligently
 * detect the catalog structure from product names.
 * 
 * Transforms this flat data:
 * ```
 * product_name,     price, qty, ...
 * Latte Sm,         4.50,  10
 * Latte Lg,         5.50,  8
 * Latte Rg,         5.00,  15
 * Oatmeal Scone,    3.50,  20
 * ```
 * 
 * Into this proper catalog structure:
 * ```
 * Sellable: Latte (base_price: 5.00, type: configurable)
 *   └─ SellableVariant: Small  (price_adjustment: -0.50, sort: 1)
 *   └─ SellableVariant: Regular (price_adjustment: 0.00, sort: 2)  
 *   └─ SellableVariant: Large  (price_adjustment: +0.50, sort: 3)
 * 
 * Sellable: Oatmeal Scone (base_price: 3.50, type: simple)
 * ```
 * 
 * This transforms a typically tedious 20-minute manual catalog setup into
 * a 2-minute review-and-confirm experience.
 */
final class CatalogExtractor extends AbstractDataExtractor
{
    protected const DEFAULT_PRIORITY = 70;
    
    /**
     * Sellable type classification.
     */
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_CONFIGURABLE = 'configurable';
    public const TYPE_BUNDLE = 'bundle';
    public const TYPE_MODIFIER = 'modifier';
    
    /**
     * Configuration options for import behavior.
     */
    public const OPT_AUTO_CREATE_VARIANTS = 'auto_create_variants';
    public const OPT_INFER_PRICES = 'infer_prices_from_data';
    public const OPT_USE_FIRST_PRICE_AS_BASE = 'use_first_price_as_base';
    public const OPT_USE_MEDIAN_PRICE_AS_BASE = 'use_median_price_as_base';
    public const OPT_AUTO_CREATE_SUPPLY = 'auto_create_supply_items';
    public const OPT_LINK_TO_EXISTING = 'link_to_existing_sellables';
    
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        private VariantInferencer $variantInferencer,
        private SellableRepository $sellableRepo,
        private SellableVariantRepository $variantRepo,
    ) {
        parent::__construct($em, $logger);
    }
    
    public function getLabel(): string
    {
        return 'Product Catalog (Sellables & Variants)';
    }
    
    public function getEntityTypes(): array
    {
        return ['sellable', 'sellable_variant', 'sellable_component'];
    }
    
    protected function getDetectionHeaders(): array
    {
        return [
            ['product', 'product_name', 'item', 'item_name', 'menu_item', 'sellable'],
            ['product_detail', 'item_detail', 'description', 'product_description'],
        ];
    }
    
    protected function getStrongIndicators(): array
    {
        return [
            'sku', 'product_sku', 'item_sku', 'upc', 'barcode',
            'price', 'unit_price', 'sell_price', 'retail_price',
            'category', 'product_category', 'menu_category', 'type',
            'cost', 'unit_cost', 'cogs',
        ];
    }
    
    /**
     * Enhanced detection that also checks for size patterns in data.
     */
    protected function scoreFromData(array $normalizedHeaders, array $sampleRows): float
    {
        $additionalScore = 0.0;
        
        $productColumn = $this->findColumn(
          ['product_name', 'product_detail', 'item_name', 'menu_item', 'product', 'item'],
          array_values($normalizedHeaders)
        );
        
        if (!$productColumn) {
            return 0.0;
        }
        
       $productNames = [];
        foreach ($sampleRows as $row) {
            $name = $row[$productColumn] ?? null;
            if ($name) {
                $productNames[] = $name;
            }
        }
        
        if (empty($productNames)) {
            return 0.0;
        }
        
        $analysis = $this->variantInferencer->analyzeDataset($productNames);
        
        if ($analysis['sized_products'] > 0) {
            $sizeRatio = $analysis['sized_products'] / $analysis['total_products'];
            $additionalScore += min(0.3, $sizeRatio * 0.4);
        }
        
        $multiVariantBases = 0;
        foreach ($analysis['base_products'] as $base) {
            if (count($base['variants']) > 1 && $base['is_sized']) {
                $multiVariantBases++;
            }
        }
        
        if ($multiVariantBases > 0) {
            $additionalScore += min(0.2, $multiVariantBases * 0.05);
        }
        
        return $additionalScore;
    }
    
    protected function extractRecords(
        array $rows,
        array $headers,
        ImportMapping $mapping
    ): ExtractionResult {
        $fieldMappings = $mapping->getFieldMappings();
        $defaultValues = $mapping->getDefaultValues() ?? [];
        $catalogOptions = $defaultValues['catalog'] ?? [];
        
        $autoCreateVariants = $catalogOptions[self::OPT_AUTO_CREATE_VARIANTS] ?? true;
        $inferPrices = $catalogOptions[self::OPT_INFER_PRICES] ?? true;
        $useMedianPrice = $catalogOptions[self::OPT_USE_MEDIAN_PRICE_AS_BASE] ?? true;
        $linkToExisting = $catalogOptions[self::OPT_LINK_TO_EXISTING] ?? true;
        
        $productColumn = $this->findColumn(
          ['product_name', 'product_detail', 'item_name', 'name',
           'product', 'sellable',
           'item', 'menu_item'],
            $headers
        );
        
        $skuColumn = $this->findColumn(
            ['sku', 'product_sku', 'item_sku', 'product_code', 'item_code'],
            $headers
        );
        
        $priceColumn = $this->findColumn(
            ['price', 'unit_price', 'sell_price', 'retail_price', 'amount'],
            $headers
        );
        
        $categoryColumn = $this->findColumn(
            ['category', 'product_category', 'menu_category', 'type', 'product_type'],
            $headers
        );
        
        $descriptionColumn = $this->findColumn(
            ['description', 'product_description', 'item_description', 'notes'],
            $headers
        );
        
        $rawProducts = [];

        foreach ($rows as $rowIndex => $row) {
            $productName = $this->getValue($row, $productColumn);
            
            if (empty($productName)) {
                continue;
            }
            
            $price = $this->getNumericValue($row, $priceColumn);
            $sku = $this->getValue($row, $skuColumn);
            $category = $this->getValue($row, $categoryColumn);
            $description = $this->getValue($row, $descriptionColumn);
            
            $key = $this->normalizeKey($productName);
            
            if (!isset($rawProducts[$key])) {
                $rawProducts[$key] = [
                    'name' => $productName,
                    'sku' => $sku,
                    'category' => $category,
                    'description' => $description,
                    'prices' => [],
                    'row_indices' => [],
                    'row_count' => 0,
                ];
            }
            
            if ($price !== null) {
                $rawProducts[$key]['prices'][] = $price;
            }
            
            $rawProducts[$key]['row_indices'][] = $rowIndex;
            $rawProducts[$key]['row_count']++;
            
            if (!$rawProducts[$key]['sku'] && $sku) {
                $rawProducts[$key]['sku'] = $sku;
            }
            if (!$rawProducts[$key]['category'] && $category) {
                $rawProducts[$key]['category'] = $category;
            }
        }
        
        $productNames = array_column($rawProducts, 'name');

        $analysis = $this->variantInferencer->analyzeDataset($productNames);
        
        $sellables = [];
        $variants = [];
        $warnings = [];
        
        foreach ($analysis['base_products'] as $productId => $baseProduct) {
            $baseName = $baseProduct['base_name'];
            $isSized = $baseProduct['is_sized'] && count($baseProduct['variants']) > 1;
            
            $allPrices = [];
            $allCategories = [];
            $allSkus = [];
            
            foreach ($baseProduct['variants'] as $variant) {
                $variantKey = $this->normalizeKey($variant['name']);
                if (isset($rawProducts[$variantKey])) {
                    $allPrices = array_merge($allPrices, $rawProducts[$variantKey]['prices']);
                    if ($rawProducts[$variantKey]['category']) {
                        $allCategories[] = $rawProducts[$variantKey]['category'];
                    }
                    if ($rawProducts[$variantKey]['sku']) {
                        $allSkus[] = $rawProducts[$variantKey]['sku'];
                    }
                }
            }
            
            $basePrice = null;
            if ($inferPrices && !empty($allPrices)) {
                $basePrice = $useMedianPrice 
                    ? $this->calculateMedian($allPrices)
                    : $allPrices[0];
            }
            
            $sellableKey = 'sellable:' . $baseName;

            $sellables[$sellableKey] = [
                'name' => $baseName,
                'type' => $isSized ? self::TYPE_CONFIGURABLE : self::TYPE_SIMPLE,
                'category' => $this->getMostCommon($allCategories),
                'base_price' => $basePrice,
                'sku' => $isSized ? null : ($allSkus[0] ?? null),
                'source' => 'inferred',
                'variant_count' => $isSized ? count($baseProduct['variants']) : 0,
                'total_rows' => array_sum(array_map(
                    fn($v) => $rawProducts[$this->normalizeKey($v['name'])]['row_count'] ?? 0,
                    $baseProduct['variants']
                )),
            ];
            
            if ($isSized && $autoCreateVariants) {
                foreach ($baseProduct['variants'] as $variantData) {
                    if ($variantData['size'] === null) {
                        continue;
                    }
                    
                    $variantKey = $this->normalizeKey($variantData['name']);
                    $originalProduct = $rawProducts[$variantKey] ?? [];
                    
                    $variantPrice = !empty($originalProduct['prices']) 
                        ? $this->calculateMedian($originalProduct['prices'])
                        : null;
                    
                    $priceAdjustment = null;
                    if ($variantPrice !== null && $basePrice !== null) {
                        $priceAdjustment = round($variantPrice - $basePrice, 2);
                    }
                    
                    $portionMultiplier = $this->inferPortionMultiplier($variantData['size']['name']);
                    
                    $variantRecordKey = 'variant:' . $baseName . ':' . $variantData['size']['code'];
                    $variants[$variantRecordKey] = [
                        'sellable_key' => $sellableKey,
                        'variant_name' => $variantData['size']['name'],
                        'original_name' => $variantData['name'],
                        'sku' => $originalProduct['sku'] ?? null,
                        'price_adjustment' => $priceAdjustment,
                        'portion_multiplier' => $portionMultiplier,
                        'sort_order' => $variantData['size']['order'],
                        'source' => 'inferred',
                        'row_count' => $originalProduct['row_count'] ?? 0,
                    ];
                }
            }
        }
        
        $diagnostics = [
            'total_rows' => count($rows),
            'unique_products_in_data' => count($rawProducts),
            'inferred_sellables' => count($sellables),
            'inferred_variants' => count($variants),
            'sized_products' => $analysis['sized_products'],
            'unsized_products' => $analysis['unsized_products'],
            'size_distribution' => $analysis['size_distribution'],
            'configurable_sellables' => count(array_filter(
                $sellables, 
                fn($s) => $s['type'] === self::TYPE_CONFIGURABLE
            )),
            'simple_sellables' => count(array_filter(
                $sellables,
                fn($s) => $s['type'] === self::TYPE_SIMPLE
            )),
            'columns_found' => array_filter([
                'product' => $productColumn,
                'sku' => $skuColumn,
                'price' => $priceColumn,
                'category' => $categoryColumn,
                'description' => $descriptionColumn,
            ]),
            'options_used' => [
                'auto_create_variants' => $autoCreateVariants,
                'infer_prices' => $inferPrices,
                'use_median_price' => $useMedianPrice,
            ],
        ];

        if (count($sellables) === count($rawProducts)) {
            $warnings[] = 'No size variants detected - all products will be created as simple sellables';
        }
        
        $skuConflicts = $this->detectSkuConflicts($sellables, $variants);
        if (!empty($skuConflicts)) {
            $warnings = array_merge($warnings, $skuConflicts);
        }
        
        return new ExtractionResult(
            records: [
                'sellables' => $sellables,
                'variants' => $variants,
            ],
            diagnostics: $diagnostics,
            warnings: $warnings,
            metadata: [
                'variant_analysis' => $analysis,
                'raw_product_count' => count($rawProducts),
                'column_mappings' => [
                    'product' => $productColumn,
                    'sku' => $skuColumn,
                    'price' => $priceColumn,
                    'category' => $categoryColumn,
                ],
            ],
        );
    }
    
    public function createEntities(array $extractedRecords, ImportBatch $batch): ServiceResponse
    {
        $sellables = $extractedRecords['sellables'] ?? [];
        $variants = $extractedRecords['variants'] ?? [];
        
        $entityCounts = [
            'sellables_created' => 0,
            'sellables_found' => 0,
            'sellables_updated' => 0,
            'variants_created' => 0,
            'variants_found' => 0,
        ];
        
        $errors = [];
        $createdSellables = [];
        
        try {
            foreach ($sellables as $key => $data) {
                $result = $this->findOrCreateSellable($data, $batch);
                
                if ($result->isSuccess()) {
                    $entity = $result->data['entity'];
                    $createdSellables[$key] = $entity;
                    
                    if ($result->data['was_created'] ?? false) {
                        $entityCounts['sellables_created']++;
                    } elseif ($result->data['was_updated'] ?? false) {
                        $entityCounts['sellables_updated']++;
                    } else {
                        $entityCounts['sellables_found']++;
                    }
                } else {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
            
            $this->em->flush();
            
            foreach ($variants as $key => $data) {
                $sellableKey = $data['sellable_key'];
                $sellable = $createdSellables[$sellableKey] ?? null;
                
                if (!$sellable) {
                    $errors[] = "Cannot create variant '{$data['variant_name']}': parent sellable not found";
                    continue;
                }
                
                $result = $this->findOrCreateVariant($data, $sellable, $batch);
                
                if ($result->isSuccess()) {
                    if ($result->data['was_created'] ?? false) {
                        $entityCounts['variants_created']++;
                    } else {
                        $entityCounts['variants_found']++;
                    }
                } else {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
            
            $this->em->flush();
            
            if (!empty($errors)) {
                return $this->failureResponse($errors, $entityCounts);
            }
            
            return ServiceResponse::success(
                data: [
                    'entity_counts' => $entityCounts,
                    'sellables' => $createdSellables,
                ],
                message: sprintf(
                    'Catalog import complete: %d sellables (%d new), %d variants (%d new)',
                    count($sellables),
                    $entityCounts['sellables_created'],
                    count($variants),
                    $entityCounts['variants_created']
                )
            );
            
        } catch (\Throwable $e) {
            $this->logger->error('Catalog entity creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->failureResponse(
                ['Catalog creation failed: ' . $e->getMessage()],
                $entityCounts
            );
        }
    }
    
    /**
     * Find or create a Sellable entity.
     */
    private function findOrCreateSellable(array $data, ImportBatch $batch): ServiceResponse
    {
        $name = trim($data['name'] ?? '');
        $sku = $data['sku'] ?? null;
        
        if (empty($name)) {
            return ServiceResponse::failure('Sellable must have a name');
        }
        
        $existing = null;
        $wasUpdated = false;
        
        if ($sku) {
            $existing = $this->sellableRepo->findOneBy(['sku' => $sku]);
        }
        
        if (!$existing) {
            $existing = $this->sellableRepo->findOneBy(['name' => $name]);
        }
        
        if ($existing) {
            if ($existing->getType() === self::TYPE_SIMPLE 
                && $data['type'] === self::TYPE_CONFIGURABLE
            ) {
                $existing->setType(self::TYPE_CONFIGURABLE);
                $wasUpdated = true;
            }
            
            return ServiceResponse::success(
                data: [
                    'entity' => $existing,
                    'was_created' => false,
                    'was_updated' => $wasUpdated,
                ]
            );
        }
        
        $sellable = new Sellable();
        $sellable->setName($name);
        $sellable->setType($data['type'] ?? self::TYPE_SIMPLE);
        $sellable->setStatus('active');
        
        if ($sku) {
            $sellable->setSku($sku);
        }
        
        if ($data['category'] ?? null) {
            $sellable->setCategory($data['category']);
        }
        
        if ($data['base_price'] !== null) {
            $sellable->setBasePrice((string) $data['base_price']);
        }
        
        if ($data['description'] ?? null) {
            $sellable->setDescription($data['description']);
        }
        
        $this->em->persist($sellable);
        
        return ServiceResponse::success(
            data: ['entity' => $sellable, 'was_created' => true]
        );
    }
    
    /**
     * Find or create a SellableVariant entity.
     */
    private function findOrCreateVariant(
        array $data,
        Sellable $sellable,
        ImportBatch $batch
    ): ServiceResponse {
        $variantName = trim($data['variant_name'] ?? '');
        $sku = $data['sku'] ?? null;
        
        if (empty($variantName)) {
            return ServiceResponse::failure('Variant must have a name');
        }
        
        $existing = null;
        
        if ($sku) {
            $existing = $this->variantRepo->findOneBy(['sku' => $sku]);
        }
        
        if (!$existing) {
            foreach ($sellable->getVariants() as $variant) {
                if (strcasecmp($variant->getVariantName(), $variantName) === 0) {
                    $existing = $variant;
                    break;
                }
            }
        }
        
        if ($existing) {
            return ServiceResponse::success(
                data: ['entity' => $existing, 'was_created' => false]
            );
        }
        
        $variant = new SellableVariant();
        $variant->setSellable($sellable);
        $variant->setVariantName($variantName);
        $variant->setStatus('active');
        
        if ($sku) {
            $variant->setSku($sku);
        }
        
        if ($data['price_adjustment'] !== null) {
            $variant->setPriceAdjustment((string) $data['price_adjustment']);
        }
        
        if ($data['portion_multiplier'] !== null) {
            $variant->setPortionMultiplier((string) $data['portion_multiplier']);
        }
        
        if ($data['sort_order'] !== null) {
            $variant->setSortOrder($data['sort_order']);
        }
        
        $sellable->addVariant($variant);
        
        $this->em->persist($variant);
        
        return ServiceResponse::success(
            data: ['entity' => $variant, 'was_created' => true]
        );
    }
    
    /**
     * Calculate median value from an array of numbers.
     */
    private function calculateMedian(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }
        
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    /**
     * Get the most common value from an array.
     */
    private function getMostCommon(array $values): ?string
    {
        $values = array_filter($values);
        
        if (empty($values)) {
            return null;
        }
        
        $counts = array_count_values($values);
        arsort($counts);
        
        return array_key_first($counts);
    }
    
    /**
     * Infer portion multiplier from size name.
     */
    private function inferPortionMultiplier(string $sizeName): ?float
    {
        $multipliers = [
            'Extra Small' => 0.5,
            'Small' => 0.75,
            'Regular' => 1.0,
            'Medium' => 1.0,
            'Large' => 1.25,
            'Extra Large' => 1.5,
            '2X Large' => 2.0,
        ];
        
        return $multipliers[$sizeName] ?? 1.0;
    }
    
    /**
     * Detect SKU conflicts in extracted data.
     */
    private function detectSkuConflicts(array $sellables, array $variants): array
    {
        $conflicts = [];
        $seenSkus = [];
        
        foreach ($sellables as $key => $data) {
            if ($data['sku']) {
                if (isset($seenSkus[$data['sku']])) {
                    $conflicts[] = sprintf(
                        'Duplicate SKU "%s" found in sellables: %s and %s',
                        $data['sku'],
                        $seenSkus[$data['sku']],
                        $data['name']
                    );
                } else {
                    $seenSkus[$data['sku']] = $data['name'];
                }
            }
        }
        
        foreach ($variants as $key => $data) {
            if ($data['sku']) {
                if (isset($seenSkus[$data['sku']])) {
                    $conflicts[] = sprintf(
                        'Duplicate SKU "%s" found: variant "%s" conflicts with "%s"',
                        $data['sku'],
                        $data['variant_name'],
                        $seenSkus[$data['sku']]
                    );
                } else {
                    $seenSkus[$data['sku']] = $data['original_name'];
                }
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Get available configuration options for UI display.
     */
    public static function getConfigurationOptions(): array
    {
        return [
            self::OPT_AUTO_CREATE_VARIANTS => [
                'label' => 'Auto-create Size Variants',
                'description' => 'Automatically detect and create size variants (Sm, Md, Lg) from product names',
                'default' => true,
                'type' => 'boolean',
            ],
            self::OPT_INFER_PRICES => [
                'label' => 'Infer Prices from Data',
                'description' => 'Calculate base prices and variant adjustments from imported price data',
                'default' => true,
                'type' => 'boolean',
            ],
            self::OPT_USE_MEDIAN_PRICE_AS_BASE => [
                'label' => 'Use Median Price as Base',
                'description' => 'Use median of all variant prices as base (vs. first price seen)',
                'default' => true,
                'type' => 'boolean',
            ],
            self::OPT_AUTO_CREATE_SUPPLY => [
                'label' => 'Auto-create Supply Items',
                'description' => 'Automatically create inventory Items for new Sellables (handled by SupplyExtractor)',
                'default' => false,
                'type' => 'boolean',
                'delegate_to' => 'SupplyExtractor',
            ],
            self::OPT_LINK_TO_EXISTING => [
                'label' => 'Link to Existing Sellables',
                'description' => 'Match imported products to existing catalog items by name or SKU',
                'default' => true,
                'type' => 'boolean',
            ],
        ];
    }
    
    /**
     * Get Sellable type options for UI display.
     */
    public static function getSellableTypes(): array
    {
        return [
            self::TYPE_SIMPLE => [
                'label' => 'Simple Item',
                'description' => 'Single product without variants',
                'icon' => 'bi-box',
            ],
            self::TYPE_CONFIGURABLE => [
                'label' => 'Configurable (with variants)',
                'description' => 'Product with size or other variants',
                'icon' => 'bi-boxes',
            ],
            self::TYPE_BUNDLE => [
                'label' => 'Bundle (combo)',
                'description' => 'Combination of multiple products',
                'icon' => 'bi-collection',
            ],
            self::TYPE_MODIFIER => [
                'label' => 'Modifier/Add-on',
                'description' => 'Optional addition to other products',
                'icon' => 'bi-plus-circle',
            ],
        ];
    }
}
