<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InitialStockFixture extends Fixture implements DependentFixtureInterface
{
    private const INITIAL_STOCK = [
        // Ingredients with opening stock
        ['item' => 'greek_yogurt', 'qty' => '5000', 'unit' => 'g', 'unit_cost' => '0.0050', 'memo' => 'Opening balance'],
        ['item' => 'granola', 'qty' => '2000', 'unit' => 'g', 'unit_cost' => '0.0040', 'memo' => 'Opening balance'],
        ['item' => 'strawberries', 'qty' => '1500', 'unit' => 'g', 'unit_cost' => '0.0060', 'memo' => 'Opening balance'],
        ['item' => 'honey', 'qty' => '500', 'unit' => 'g', 'unit_cost' => '0.0100', 'memo' => 'Opening balance'],
        ['item' => 'all_purpose_flour', 'qty' => '10000', 'unit' => 'g', 'unit_cost' => '0.0010', 'memo' => 'Opening balance'],
        ['item' => 'sugar', 'qty' => '5000', 'unit' => 'g', 'unit_cost' => '0.0012', 'memo' => 'Opening balance'],
        ['item' => 'unsalted_butter', 'qty' => '3000', 'unit' => 'g', 'unit_cost' => '0.0080', 'memo' => 'Opening balance'],
        ['item' => 'eggs', 'qty' => '120', 'unit' => 'ea', 'unit_cost' => '0.2000', 'memo' => 'Opening balance'],
        ['item' => 'blueberries', 'qty' => '2000', 'unit' => 'g', 'unit_cost' => '0.0070', 'memo' => 'Opening balance'],
        ['item' => 'yeast', 'qty' => '200', 'unit' => 'g', 'unit_cost' => '0.0150', 'memo' => 'Opening balance'],
        ['item' => 'milk', 'qty' => '4000', 'unit' => 'ml', 'unit_cost' => '0.0010', 'memo' => 'Opening balance'],
        ['item' => 'chocolate', 'qty' => '2500', 'unit' => 'g', 'unit_cost' => '0.0120', 'memo' => 'Opening balance'],
        ['item' => 'salt', 'qty' => '500', 'unit' => 'g', 'unit_cost' => '0.0005', 'memo' => 'Opening balance'],
        
        // Packaging
        ['item' => '12oz_clear_cup', 'qty' => '200', 'unit' => 'ea', 'unit_cost' => '0.1000', 'memo' => 'Opening balance'],
        ['item' => 'dome_lid', 'qty' => '200', 'unit' => 'ea', 'unit_cost' => '0.0700', 'memo' => 'Opening balance'],
        ['item' => 'muffin_liner', 'qty' => '400', 'unit' => 'ea', 'unit_cost' => '0.0300', 'memo' => 'Opening balance'],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();
        $today = new \DateTime();
        
        foreach (self::INITIAL_STOCK as $stockData) {
            $stockTarget = $this->getReference('stock_target_' . $stockData['item'], StockTarget::class);
            $unit = $manager->getRepository(Unit::class)
                ->findOneBy(['abbreviation' => $stockData['unit']]);
            
            // Create stock transaction for opening balance
            $transaction = new StockTransaction();
            $transaction->setStockTarget($stockTarget);
            $transaction->setUseType('opening_balance');
            $transaction->setQty($stockData['qty']);
            $transaction->setUnit($unit);
            $transaction->setUnitCost($stockData['unit_cost']);
            $transaction->setReason($stockData['memo']);
            $transaction->setEffectiveDate($today);
            $transaction->setRecordedAt($now);
            $transaction->setStatus('posted');
            
            $manager->persist($transaction);
            
            // Update stock target current quantity
            $currentQty = (float)$stockTarget->getCurrentQty();
            $newQty = $currentQty + (float)$stockData['qty'];
            $stockTarget->setCurrentQty((string)$newQty);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            StockTargetsFixture::class,
        ];
    }
}
