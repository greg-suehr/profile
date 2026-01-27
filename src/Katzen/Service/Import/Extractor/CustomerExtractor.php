<?php

namespace App\Katzen\Service\Import\Extractor;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Import\ImportBatch;
use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Service\Import\ExtractionResult;
use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Customer Extractor
 * 
 * Extracts and manages Customer entities from import data, with intelligent
 * handling of anonymous/walk-in transactions common in food service:
 * 
 * - POS exports often lack customer data (cash sales, quick service)
 * - E-commerce imports may have generic "Guest" or platform customers
 * - Event sales might all attribute to a single event (County Fair, Farmers Market)
 * 
 * The extractor supports configurable "fallback customers" that serve as
 * catch-all attribution for transactions without explicit customer data:
 * 
 * - "Walk In" - Default for anonymous retail/counter sales
 * - "Shopify Customer" - Platform-level attribution for e-commerce
 * - "County Fair" / "Farmers Market" - Event-based attribution
 * - Custom fallbacks defined by the user
 * 
 * This enables proper order tracking and reporting even when individual
 * customer identity isn't captured, while preserving the ability to
 * extract and create real Customer records when data is present.
 * 
 * @example Configuration in ImportMapping:
 * ```php
 * $mapping->setDefaultValues([
 *     'customer' => [
 *         'fallback_mode' => 'walk_in',        # or 'named', 'platform', 'event'
 *         'fallback_customer_id' => 123,       # specific customer ID
 *         'fallback_customer_name' => 'Walk In', # or create by name
 *         'create_if_missing' => true,         # auto-create fallback if needed
 *     ],
 * ]);
 * ```
 */
final class CustomerExtractor extends AbstractDataExtractor
{
    /**
     * Well-known fallback customer types.
     * These can be auto-created if they don't exist.
     */
    public const FALLBACK_WALK_IN = 'walk_in';
    public const FALLBACK_PLATFORM = 'platform';
    public const FALLBACK_EVENT = 'event';
    public const FALLBACK_NAMED = 'named';
    public const FALLBACK_NONE = 'none';
    
    /**
     * Default names for well-known fallback types.
     */
    private const FALLBACK_DEFAULTS = [
        self::FALLBACK_WALK_IN => [
            'name' => 'Walk In',
            'description' => 'Anonymous walk-in customers (counter sales, cash transactions)',
            'is_system' => true,
        ],
        self::FALLBACK_PLATFORM => [
            'name' => 'Online Customer',
            'description' => 'Aggregated online/e-commerce orders',
            'is_system' => true,
        ],
        self::FALLBACK_EVENT => [
            'name' => 'Event Sales',
            'description' => 'Sales from events, markets, and pop-ups',
            'is_system' => true,
        ],
    ];
    
    /**
     * Platform-specific fallback names (auto-detected from data).
     */
    private const PLATFORM_NAMES = [
        'shopify' => 'Shopify Customer',
        'square' => 'Square Customer', 
        'toast' => 'Toast Customer',
        'clover' => 'Clover Customer',
        'doordash' => 'DoorDash Customer',
        'ubereats' => 'Uber Eats Customer',
        'grubhub' => 'Grubhub Customer',
    ];
    
    protected const DEFAULT_PRIORITY = 80;
    
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        private CustomerRepository $customerRepo,
    ) {
        parent::__construct($em, $logger);
    }
    
    public function getLabel(): string
    {
        return 'Customers & Attribution';
    }
    
    public function getEntityTypes(): array
    {
        return ['customer'];
    }
    
    protected function getDetectionHeaders(): array
    {
        return [
            ['customer', 'customer_name', 'client', 'buyer', 'patron'],
            ['order', 'order_id', 'transaction', 'sale', 'invoice'],
        ];
    }
    
    protected function getStrongIndicators(): array
    {
        return [
            'customer_id', 'customer_email', 'customer_phone',
            'billing_name', 'shipping_name', 'contact_name',
            'company', 'organization', 'account',
        ];
    }
    
    /**
     * Override detection to handle the "no customer column" case.
     * 
     * Returns a moderate score even without customer columns if we detect
     * transaction data - this allows fallback customer assignment.
     */
    public function detect(array $headers, array $sampleRows): float
    {
        $normalizedHeaders = $this->normalizeHeaders($headers);
        
        $hasCustomerColumn = $this->hasAnyHeader($normalizedHeaders, [
            'customer', 'customer_name', 'client', 'buyer', 
            'customer_id', 'customer_email', 'patron',
        ]);
        
        if ($hasCustomerColumn) {
            return parent::detect($headers, $sampleRows);
        }
        
        $hasTransactionIndicators = $this->hasAnyHeader($normalizedHeaders, [
            'order', 'order_id', 'order_number', 'transaction', 'transaction_id',
            'sale', 'sale_id', 'invoice', 'receipt', 'ticket',
        ]);
        
        $hasDateColumn = $this->hasAnyHeader($normalizedHeaders, [
            'date', 'order_date', 'transaction_date', 'sale_date', 'created_at',
        ]);
        
        if ($hasTransactionIndicators && $hasDateColumn) {
            return 0.4;
        }
        
        return 0.0;
    }
    
    protected function extractRecords(
        array $rows,
        array $headers,
        ImportMapping $mapping
    ): ExtractionResult {
        $fieldMappings = $mapping->getFieldMappings();
        $defaultValues = $mapping->getDefaultValues() ?? [];
        $customerDefaults = $defaultValues['customer'] ?? [];
        
        $fallbackMode = $customerDefaults['fallback_mode'] ?? self::FALLBACK_WALK_IN;
        $fallbackName = $customerDefaults['fallback_customer_name'] ?? null;
        $fallbackId = $customerDefaults['fallback_customer_id'] ?? null;
        
        $customerColumn = $this->findColumn(
            ['customer', 'customer_name', 'client', 'buyer', 'patron'],
            $headers
        );
        $emailColumn = $this->findColumn(
            ['customer_email', 'email', 'contact_email'],
            $headers
        );
        $phoneColumn = $this->findColumn(
            ['customer_phone', 'phone', 'contact_phone', 'telephone'],
            $headers
        );
        $companyColumn = $this->findColumn(
            ['company', 'organization', 'business', 'account'],
            $headers
        );
        
        $detectedPlatform = $this->detectPlatform($rows, $headers);
        
        $customers = [];
        $anonymousCount = 0;
        $extractedCount = 0;
        $platformCounts = [];
        
        foreach ($rows as $rowIndex => $row) {
            $customerName = $this->getValue($row, $customerColumn);
            $email = $this->getValue($row, $emailColumn);
            $phone = $this->getValue($row, $phoneColumn);
            $company = $this->getValue($row, $companyColumn);
            
            $hasCustomerData = $this->hasIdentifiableCustomer($customerName, $email, $phone);
            
            if ($hasCustomerData) {
                $key = $this->buildCustomerKey($customerName, $email);
                
                if (!isset($customers[$key])) {
                    $customers[$key] = [
                        'name' => $customerName ?: $this->deriveNameFromEmail($email),
                        'email' => $email,
                        'phone' => $phone,
                        'company' => $company,
                        'source' => 'extracted',
                        'row_indices' => [],
                    ];
                }
                
                $customers[$key]['row_indices'][] = $rowIndex;
                $extractedCount++;
                
            } else {
                $anonymousCount++;
                
                if ($detectedPlatform) {
                    $platformCounts[$detectedPlatform] = ($platformCounts[$detectedPlatform] ?? 0) + 1;
                }
            }
        }
        
        $fallbackInfo = $this->resolveFallback(
            $fallbackMode,
            $fallbackName,
            $fallbackId,
            $detectedPlatform,
            $anonymousCount
        );
        
        $diagnostics = [
            'total_rows' => count($rows),
            'extracted_customers' => count($customers),
            'extracted_transactions' => $extractedCount,
            'anonymous_transactions' => $anonymousCount,
            'fallback_mode' => $fallbackMode,
            'fallback_customer' => $fallbackInfo['name'] ?? null,
            'detected_platform' => $detectedPlatform,
            'platform_counts' => $platformCounts,
            'has_customer_column' => $customerColumn !== null,
        ];
        
        if ($anonymousCount > 0 && $fallbackInfo) {
            $fallbackKey = '__fallback__';
            $customers[$fallbackKey] = array_merge($fallbackInfo, [
                'source' => 'fallback',
                'anonymous_count' => $anonymousCount,
            ]);
        }
        
        $warnings = [];
        if ($anonymousCount > 0 && !$fallbackInfo) {
            $warnings[] = "{$anonymousCount} transactions have no customer data and no fallback configured";
        }
        
        return new ExtractionResult(
            records: ['customers' => $customers],
            diagnostics: $diagnostics,
            warnings: $warnings,
            metadata: [
                'fallback_info' => $fallbackInfo,
                'column_mappings' => [
                    'customer' => $customerColumn,
                    'email' => $emailColumn,
                    'phone' => $phoneColumn,
                    'company' => $companyColumn,
                ],
            ],
        );
    }
    
    public function createEntities(array $extractedRecords, ImportBatch $batch): ServiceResponse
    {
        $customers = $extractedRecords['customers'] ?? [];
        $entityCounts = ['customers_created' => 0, 'customers_found' => 0];
        $errors = [];
        
        try {
            foreach ($customers as $key => $data) {
                $result = $this->findOrCreateCustomer($data, $batch);
                
                if ($result->isSuccess()) {
                    if ($result->data['was_created'] ?? false) {
                        $entityCounts['customers_created']++;
                    } else {
                        $entityCounts['customers_found']++;
                    }
                } else {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
            
            $this->em->flush();
            
            if (!empty($errors)) {
                return $this->failureResponse($errors, $entityCounts);
            }
            
            return $this->successResponse(
                $entityCounts,
                sprintf(
                    'Processed %d customers (%d created, %d existing)',
                    count($customers),
                    $entityCounts['customers_created'],
                    $entityCounts['customers_found']
                )
            );
            
        } catch (\Throwable $e) {
            $this->logger->error('Customer entity creation failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->failureResponse(
                ['Customer creation failed: ' . $e->getMessage()],
                $entityCounts
            );
        }
    }
    
    
    /**
     * Check if a row has identifiable customer data.
     */
    private function hasIdentifiableCustomer(?string $name, ?string $email, ?string $phone): bool
    {
        if ($name !== null && !$this->isGenericCustomerName($name)) {
            return true;
        }
        
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        
        if ($phone !== null && preg_match('/\d{7,}/', preg_replace('/\D/', '', $phone))) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a customer name is a generic placeholder.
     */
    private function isGenericCustomerName(string $name): bool
    {
        $genericNames = [
            'guest', 'walk in', 'walk-in', 'walkin', 'anonymous',
            'cash', 'cash customer', 'counter', 'retail',
            'n/a', 'na', 'none', 'unknown', '-', '.',
        ];
        
        return in_array(strtolower(trim($name)), $genericNames);
    }
    
    /**
     * Build a unique key for customer deduplication.
     */
    private function buildCustomerKey(?string $name, ?string $email): string
    {
        if ($email) {
            return 'email:' . strtolower(trim($email));
        }
        
        return 'name:' . $this->normalizeKey($name ?? 'unknown');
    }
    
    /**
     * Derive a customer name from email address.
     */
    private function deriveNameFromEmail(?string $email): string
    {
        if (!$email) {
            return 'Unknown Customer';
        }
        
        $localPart = explode('@', $email)[0];
        
        $name = str_replace(['.', '_', '-'], ' ', $localPart);
        $name = ucwords($name);
        
        return $name;
    }
    
    /**
     * Detect the source platform from data patterns.
     */
    private function detectPlatform(array $rows, array $headers): ?string
    {
        $normalizedHeaders = $this->normalizeHeaders($headers);
        
        $headerString = strtolower(implode(' ', array_keys($normalizedHeaders)));
        
        foreach (array_keys(self::PLATFORM_NAMES) as $platform) {
            if (str_contains($headerString, $platform)) {
                return $platform;
            }
        }
        
        $sampleSize = min(100, count($rows));
        $sampleText = '';
        
        for ($i = 0; $i < $sampleSize; $i++) {
            $sampleText .= strtolower(implode(' ', array_values($rows[$i] ?? [])));
        }
        
        foreach (array_keys(self::PLATFORM_NAMES) as $platform) {
            if (str_contains($sampleText, $platform)) {
                return $platform;
            }
        }
        
        return null;
    }
    
    /**
     * Resolve the fallback customer configuration.
     */
    private function resolveFallback(
        string $mode,
        ?string $customName,
        ?int $customId,
        ?string $detectedPlatform,
        int $anonymousCount
    ): ?array {
        if ($anonymousCount === 0) {
            return null;
        }
        
        if ($mode === self::FALLBACK_NONE) {
            return null;
        }
        
        if ($mode === self::FALLBACK_NAMED) {
            return [
                'id' => $customId,
                'name' => $customName ?? 'Custom Fallback',
                'mode' => $mode,
            ];
        }
        
        if ($mode === self::FALLBACK_PLATFORM && $detectedPlatform) {
            return [
                'name' => self::PLATFORM_NAMES[$detectedPlatform] ?? 'Platform Customer',
                'mode' => $mode,
                'platform' => $detectedPlatform,
                'description' => "Orders imported from {$detectedPlatform}",
            ];
        }
        
        if (isset(self::FALLBACK_DEFAULTS[$mode])) {
            $defaults = self::FALLBACK_DEFAULTS[$mode];
            return [
                'name' => $customName ?? $defaults['name'],
                'description' => $defaults['description'],
                'mode' => $mode,
                'is_system' => $defaults['is_system'],
            ];
        }
        
        $defaults = self::FALLBACK_DEFAULTS[self::FALLBACK_WALK_IN];
        return [
            'name' => $customName ?? $defaults['name'],
            'description' => $defaults['description'],
            'mode' => self::FALLBACK_WALK_IN,
            'is_system' => true,
        ];
    }
    
    /**
     * Find or create a Customer entity.
     */
    private function findOrCreateCustomer(array $data, ImportBatch $batch): ServiceResponse
    {
        $name = trim($data['name'] ?? '');
        $email = $data['email'] ?? null;
        
        if (empty($name) && empty($email)) {
            return ServiceResponse::failure('Customer must have a name or email');
        }
        
        $existing = null;
        
        if ($email) {
            $existing = $this->customerRepo->findOneBy(['email' => $email]);
        }
        
        if (!$existing && $name) {
            $existing = $this->customerRepo->findOneBy(['name' => $name]);
        }
        
        if ($existing) {
            return ServiceResponse::success(
                data: ['entity' => $existing, 'was_created' => false]
            );
        }
        
        $customer = new Customer();
        $customer->setName($name ?: $this->deriveNameFromEmail($email));
        
        if ($email) {
            $customer->setEmail($email);
        }
        
        if ($data['phone'] ?? null) {
            $customer->setPhone($data['phone']);
        }
        
        if ($data['is_system'] ?? false) {
          # TODO: If Customer entity has a system flag, set it
          # $customer->setIsSystem(true);
        }
        
        if ($data['source'] === 'fallback' && ($data['description'] ?? null)) {
          # TODO: If Customer has a notes field
          # $customer->setNotes($data['description']);
        }
        
        $this->em->persist($customer);
        
        return ServiceResponse::success(
            data: ['entity' => $customer, 'was_created' => true]
        );
    }
        
    /**
     * Get available fallback modes for UI display.
     */
    public static function getFallbackModes(): array
    {
        return [
            self::FALLBACK_WALK_IN => [
                'label' => 'Walk In Customer',
                'description' => 'Assign anonymous transactions to a "Walk In" customer for counter/cash sales',
            ],
            self::FALLBACK_PLATFORM => [
                'label' => 'Platform Customer',
                'description' => 'Auto-detect platform (Shopify, Square, etc.) and create platform-specific customer',
            ],
            self::FALLBACK_EVENT => [
                'label' => 'Event Sales',
                'description' => 'Assign to an event customer (farmers market, fair, pop-up)',
            ],
            self::FALLBACK_NAMED => [
                'label' => 'Specific Customer',
                'description' => 'Assign to a customer you specify by name or ID',
            ],
            self::FALLBACK_NONE => [
                'label' => 'No Fallback',
                'description' => 'Skip transactions without customer data (may cause import errors)',
            ],
        ];
    }
    
    /**
     * Get well-known platform names.
     */
    public static function getPlatformNames(): array
    {
        return self::PLATFORM_NAMES;
    }
}
