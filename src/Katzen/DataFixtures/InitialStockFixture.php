<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\StockLot;
use App\Katzen\Entity\StockLotLocationBalance;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use App\Katzen\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InitialStockFixture extends Fixture implements DependentFixtureInterface
{
    private const INITIAL_STOCK = [
        // Ingredients with opening stock - Main Location
        ['item' => 'greek_yogurt', 'qty' => '5000', 'unit' => 'g', 'unit_cost' => '0.0050', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'granola', 'qty' => '2000', 'unit' => 'g', 'unit_cost' => '0.0040', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'strawberries', 'qty' => '1500', 'unit' => 'g', 'unit_cost' => '0.0060', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'honey', 'qty' => '500', 'unit' => 'g', 'unit_cost' => '0.0100', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'all_purpose_flour', 'qty' => '10000', 'unit' => 'g', 'unit_cost' => '0.0010', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'sugar', 'qty' => '5000', 'unit' => 'g', 'unit_cost' => '0.0012', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'unsalted_butter', 'qty' => '3000', 'unit' => 'g', 'unit_cost' => '0.0080', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'eggs', 'qty' => '120', 'unit' => 'ea', 'unit_cost' => '0.2000', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'blueberries', 'qty' => '2000', 'unit' => 'g', 'unit_cost' => '0.0070', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'yeast', 'qty' => '200', 'unit' => 'g', 'unit_cost' => '0.0150', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'milk', 'qty' => '4000', 'unit' => 'ml', 'unit_cost' => '0.0010', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'chocolate', 'qty' => '2500', 'unit' => 'g', 'unit_cost' => '0.0120', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'salt', 'qty' => '500', 'unit' => 'g', 'unit_cost' => '0.0005', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'espresso_beans', 'qty' => '4000', 'unit' => 'g', 'unit_cost' => '0.0880', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'drip_coffee_beans', 'qty' => '2500', 'unit' => 'g', 'unit_cost' => '0.0740', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'whole_milk', 'qty' => '8000', 'unit' => 'ml', 'unit_cost' => '0.0010', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'oat_milk', 'qty' => '400', 'unit' => 'ml', 'unit_cost' => '0.0014', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'heavy_cream', 'qty' => '1200', 'unit' => 'ml',  'unit_cost' => '0.0031', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'vanilla_syrup', 'qty' => '800', 'unit' => 'ml', 'unit_cost' => '0.0210', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'caramel_syrup', 'qty' => '1400', 'unit' => 'ml', 'unit_cost' => '0.0210', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'cocoa_powder', 'qty' => '600', 'unit' => 'g', 'unit_cost' => '0.03', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'cinnamon', 'qty' => '320', 'unit' => 'g', 'unit_cost' => '0.042', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        
        // Packaging - Main Location
        ['item' => '8oz_paper_cup', 'qty' => '300', 'unit' => 'ea', 'unit_cost' => '0.0600', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => '12oz_paper_cup', 'qty' => '500', 'unit' => 'ea', 'unit_cost' => '0.0800', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => '16oz_paper_cup', 'qty' => '190', 'unit' => 'ea', 'unit_cost' => '0.0800', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'hot_drink_lid', 'qty' => '600', 'unit' => 'ea', 'unit_cost' => '0.0400', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => '12oz_clear_cup', 'qty' => '200', 'unit' => 'ea', 'unit_cost' => '0.1000', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => '16oz_clear_cup', 'qty' => '120', 'unit' => 'ea', 'unit_cost' => '0.1000', 'location' => 'main', 'memo' => 'Opening balance - Main location'],        
        ['item' => 'dome_lid', 'qty' => '170', 'unit' => 'ea', 'unit_cost' => '0.0700', 'location' => 'main', 'memo' => 'Opening balance - Main location'],
        ['item' => 'muffin_liner', 'qty' => '400', 'unit' => 'ea', 'unit_cost' => '0.0300', 'location' => 'main', 'memo' => 'Opening balance - Main location'],

        // Truck Location - Smaller quantities ready for mobile sales
        ['item' => 'greek_yogurt', 'qty' => '1000', 'unit' => 'g', 'unit_cost' => '0.0050', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'granola', 'qty' => '500', 'unit' => 'g', 'unit_cost' => '0.0040', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'strawberries', 'qty' => '300', 'unit' => 'g', 'unit_cost' => '0.0060', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'honey', 'qty' => '100', 'unit' => 'g', 'unit_cost' => '0.0100', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'blueberries', 'qty' => '400', 'unit' => 'g', 'unit_cost' => '0.0070', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'milk', 'qty' => '1000', 'unit' => 'ml', 'unit_cost' => '0.0010', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        
        // Packaging for Truck
        ['item' => '12oz_clear_cup', 'qty' => '100', 'unit' => 'ea', 'unit_cost' => '0.1000', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
        ['item' => 'dome_lid', 'qty' => '100', 'unit' => 'ea', 'unit_cost' => '0.0700', 'location' => 'truck', 'memo' => 'Initial stock for food truck'],
    ];

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();
        $today = new \DateTime();
        
        // Create Stock Locations first
        $mainLocation = $this->createStockLocation($manager, 'MAIN', 'Main', '123 Main Street, Pittsburgh, PA 15222');
        $truckLocation = $this->createStockLocation($manager, 'TRUCK', 'Truck', 'Mobile Food Truck');
        
        $manager->flush();
        
        // Store references for later use
        $this->addReference('location_main', $mainLocation);
        $this->addReference('location_truck', $truckLocation);
        
        // Create a map of locations
        $locations = [
            'main' => $mainLocation,
            'truck' => $truckLocation,
        ];
        
        // Process initial stock with proper lot tracking
        foreach (self::INITIAL_STOCK as $stockData) {
            $stockTarget = $this->getReference('stock_target_' . $stockData['item'], StockTarget::class);
            $location = $locations[$stockData['location']];
            
            $unit = $manager->getRepository(Unit::class)
                ->findOneBy(['abbreviation' => $stockData['unit']]);
            
            // Generate a lot number for this opening balance
            $lotNumber = 'OPEN-2024-' . strtoupper($stockData['item']) . '-' . strtoupper($stockData['location']);
            
            // Create StockLot for this inventory
            $stockLot = new StockLot();
            $stockLot->setStockTarget($stockTarget);
            $stockLot->setLotNumber($lotNumber);
            $stockLot->setReceivedDate($today);
            $stockLot->setInitialQty($stockData['qty']);
            $stockLot->setCurrentQty($stockData['qty']);
            $stockLot->setReservedQty('0.00');
            $stockLot->setUnitCost($stockData['unit_cost']);
            $stockLot->setNotes($stockData['memo']);
            $stockLot->setCreatedAt($now);
            $stockLot->setUpdatedAt($today);
          
            // Set expiration dates for perishable items (30 days from now for produce/dairy)
            if (in_array($stockData['item'], ['greek_yogurt', 'strawberries', 'blueberries', 'milk', 'unsalted_butter', 'eggs'])) {
                $expirationDate = (clone $today)->modify('+30 days');
                $stockLot->setExpirationDate($expirationDate);
            }
            
            $manager->persist($stockLot);
            
            // Create StockLotLocationBalance
            $locationBalance = new StockLotLocationBalance();
            $locationBalance->setStockLot($stockLot);
            $locationBalance->setLocation($location);
            $locationBalance->setQty($stockData['qty']);
            $locationBalance->setReservedQty('0.00');
            $locationBalance->setUpdatedAt($today);
            
            $manager->persist($locationBalance);
            
            // Create stock transaction for opening balance
            $transaction = new StockTransaction();
            $transaction->setStockTarget($stockTarget);
            $transaction->setUseType('opening_balance');
            $transaction->setQty($stockData['qty']);
            $transaction->setUnit($unit);
            $transaction->setUnitCost($stockData['unit_cost']);
            $transaction->setLotNumber($lotNumber);
            $transaction->setReason($stockData['memo']);
            $transaction->setEffectiveDate($today);
            $transaction->setRecordedAt($now);
            $transaction->setStatus('posted');
            
            if (in_array($stockData['item'], ['greek_yogurt', 'strawberries', 'blueberries', 'milk', 'unsalted_butter', 'eggs'])) {
                $expirationDate = (clone $today)->modify('+30 days');
                $transaction->setExpirationDate($expirationDate);
            }
            
            $manager->persist($transaction);
            
            // Update stock target current quantity (aggregate across all locations)
            $currentQty = (float)$stockTarget->getCurrentQty();
            $newQty = $currentQty + (float)$stockData['qty'];
            $stockTarget->setCurrentQty((string)$newQty);
        }
        
        $manager->flush();
    }

    /**
     * Create a StockLocation entity
     */
    private function createStockLocation(
        ObjectManager $manager, 
        string $code, 
        string $name, 
        ?string $address = null
    ): StockLocation {
        // Check if location already exists
        $existing = $manager->getRepository(StockLocation::class)
            ->findOneBy(['code' => $code]);
        
        if ($existing) {
            return $existing;
        }
        
        $location = new StockLocation();
        $location->setCode($code);
        $location->setName($name);
        $location->setAddress($address);
        
        $manager->persist($location);
        
        return $location;
    }

    public function getDependencies(): array
    {
        return [
            StockTargetsFixture::class,
        ];
    }
}
