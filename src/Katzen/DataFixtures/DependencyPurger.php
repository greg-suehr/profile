<?php

namespace App\Katzen\DataFixtures;

use Doctrine\ORM\EntityManagerInterface;

class DependencyPurger
{
    private const DELETE_ORDER = [
        // Level 1 - No dependencies or only up-references
        'App\Katzen\Entity\LedgerEntryLine',
        'App\Katzen\Entity\StockLotLocationBalance',
        'App\Katzen\Entity\StockLotTransfer',
        'App\Katzen\Entity\StockTransaction',
        'App\Katzen\Entity\RecipeIngredient',
        'App\Katzen\Entity\OrderItem',
        'App\Katzen\Entity\InvoiceLineItem',
        'App\Katzen\Entity\PurchaseItem',
        'App\Katzen\Entity\StockReceiptItem',
        'App\Katzen\Entity\VendorInvoiceItem',
        
        // Level 2 - Depends on Level 1
        'App\Katzen\Entity\StockLot',
        'App\Katzen\Entity\StockReceipt',
        'App\Katzen\Entity\Order',
        'App\Katzen\Entity\Invoice',
        'App\Katzen\Entity\LedgerEntry',
        'App\Katzen\Entity\Recipe',
        'App\Katzen\Entity\RecipeList',
        'App\Katzen\Entity\Sellable',
        
        // Level 3 - Depends on Level 2
        'App\Katzen\Entity\Purchase',
        'App\Katzen\Entity\VendorInvoice',
        'App\Katzen\Entity\StockTarget',
        'App\Katzen\Entity\StockTargetRule',  
        'App\Katzen\Entity\SellableVariant',
        'App\Katzen\Entity\SellableComponent',
        'App\Katzen\Entity\SellableModifierGroup',
        'App\Katzen\Entity\PriceRule',
        'App\Katzen\Entity\CustomerPriceOverride',
        
        // Level 4 - Core entities
        'App\Katzen\Entity\Item',
        'App\Katzen\Entity\Customer',
        'App\Katzen\Entity\Vendor',
        'App\Katzen\Entity\StockLocation',
        'App\Katzen\Entity\Account',
        'App\Katzen\Entity\Unit',

        //Level 5 - System logs
        'App\Katzen\Entity\PriceHistory',
        'App\Katzen\Entity\UnitConversionLog',
        'App\Katzen\Entity\Tag',        
        'App\Katzen\Entity\ChangeLog',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function purge(): void
    {
        // Disable foreign key checks
        $this->em->getConnection()->executeStatement('SET session_replication_role = replica');
        
        try {
            foreach (self::DELETE_ORDER as $entityClass) {
                $cmd = $this->em->getClassMetadata($entityClass);
                $connection = $this->em->getConnection();
                
                $this->em->createQuery("DELETE FROM {$entityClass} e")->execute();                
            }
            
            $this->em->flush();
        } finally {
            // Re-enable foreign key checks
            $this->em->getConnection()->executeStatement('SET session_replication_role = DEFAULT');
        }
    }
}
