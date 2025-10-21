<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class StockTargetsFixture extends Fixture implements DependentFixtureInterface
{
    private const STOCK_TARGETS = [
        // Ingredients
        ['item' => 'greek_yogurt', 'unit' => 'g', 'reorder_point' => '500.00'],
        ['item' => 'granola', 'unit' => 'g', 'reorder_point' => '200.00'],
        ['item' => 'strawberries', 'unit' => 'g', 'reorder_point' => '200.00'],
        ['item' => 'honey', 'unit' => 'g', 'reorder_point' => '50.00'],
        ['item' => 'all_purpose_flour', 'unit' => 'g', 'reorder_point' => '1000.00'],
        ['item' => 'sugar', 'unit' => 'g', 'reorder_point' => '500.00'],
        ['item' => 'unsalted_butter', 'unit' => 'g', 'reorder_point' => '300.00'],
        ['item' => 'eggs', 'unit' => 'ea', 'reorder_point' => '12.00'],
        ['item' => 'blueberries', 'unit' => 'g', 'reorder_point' => '200.00'],
        ['item' => 'yeast', 'unit' => 'g', 'reorder_point' => '20.00'],
        ['item' => 'milk', 'unit' => 'ml', 'reorder_point' => '500.00'],
        ['item' => 'chocolate', 'unit' => 'g', 'reorder_point' => '250.00'],
        ['item' => 'salt', 'unit' => 'g', 'reorder_point' => '50.00'],
        
        // Packaging
        ['item' => '12oz_clear_cup', 'unit' => 'ea', 'reorder_point' => '20.00'],
        ['item' => 'dome_lid', 'unit' => 'ea', 'reorder_point' => '20.00'],
        ['item' => 'muffin_liner', 'unit' => 'ea', 'reorder_point' => '40.00'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::STOCK_TARGETS as $targetData) {
            $item = $this->getReference('item_' . $targetData['item'], Item::class);
            
            // Check if stock target already exists for this item
            $existing = $manager->getRepository(StockTarget::class)
                ->findOneBy(['item' => $item]);
            
            if ($existing) {
                $stockTarget = $existing;
            } else {
                $stockTarget = new StockTarget();
                $stockTarget->setItem($item);
            }
            
            $stockTarget->setName($item->getName());
            
            $baseUnit = $manager->getRepository(Unit::class)
                ->findOneBy(['abbreviation' => $targetData['unit']]);
            $stockTarget->setBaseUnit($baseUnit);
            
            $stockTarget->setCurrentQty('0.00');
            $stockTarget->setReorderPoint($targetData['reorder_point']);
            $stockTarget->setStatus('active');
            
            $manager->persist($stockTarget);
            
            // Store reference for InitialStockFixture
            $this->addReference('stock_target_' . $targetData['item'], $stockTarget);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ItemsFixture::class,
        ];
    }
}
