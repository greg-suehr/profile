<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\SellableComponent;
use App\Katzen\Entity\SellableVariant;
use App\Katzen\Entity\SellableModifierGroup;
use App\Katzen\Entity\StockTarget;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SellablesFixture extends Fixture implements DependentFixtureInterface
{
    private const SELLABLES = [
        'Fruit Parfait' => [
            'type' => 'simple',
            'category' => 'breakfast',
            'description' => 'Fresh yogurt parfait with granola and berries',
            'base_price' => '8.99',
            'components' => [
                ['target' => 'greek_yogurt', 'qty' => '200.0000', 'purpose' => 'primary'],
                ['target' => 'granola', 'qty' => '50.0000', 'purpose' => 'component'],
                ['target' => 'strawberries', 'qty' => '60.0000', 'purpose' => 'component'],
                ['target' => 'honey', 'qty' => '10.0000', 'purpose' => 'garnish'],
            ],
            'variants' => [
                ['name' => 'Small Parfait', 'sku' => 'PARF-SM', 'price_adj' => '-2.00', 'portion_mult' => '0.75'],
                ['name' => 'Regular Parfait', 'sku' => 'PARF-REG', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
                ['name' => 'Large Parfait', 'sku' => 'PARF-LG', 'price_adj' => '2.50', 'portion_mult' => '1.50'],
            ],
        ],
        
        'Blueberry Muffin' => [
            'type' => 'simple',
            'category' => 'bakery',
            'description' => 'Fresh-baked blueberry muffin',
            'base_price' => '3.50',
            'components' => [
                ['target' => 'all_purpose_flour', 'qty' => '60.0000', 'purpose' => 'primary'],
                ['target' => 'blueberries', 'qty' => '30.0000', 'purpose' => 'component'],
                ['target' => 'sugar', 'qty' => '25.0000', 'purpose' => 'component'],
                ['target' => 'unsalted_butter', 'qty' => '15.0000', 'purpose' => 'component'],
            ],
            'variants' => [
                ['name' => 'Regular Muffin', 'sku' => 'MUFF-BLU', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
            ],
        ],
        
        'Espresso' => [
            'type' => 'configurable',
            'category' => 'beverage',
            'description' => 'Rich, bold espresso shot',
            'base_price' => '3.00',
            'components' => [
                ['target' => 'espresso_beans', 'qty' => '18.0000', 'purpose' => 'primary'],
            ],
            'variants' => [
                ['name' => 'Single Shot', 'sku' => 'ESP-SINGLE', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
                ['name' => 'Double Shot', 'sku' => 'ESP-DOUBLE', 'price_adj' => '1.50', 'portion_mult' => '2.00'],
            ],
        ],
        
        'Latte' => [
            'type' => 'configurable',
            'category' => 'beverage',
            'description' => 'Espresso with steamed milk',
            'base_price' => '4.50',
            'components' => [
                ['target' => 'espresso_beans', 'qty' => '18.0000', 'purpose' => 'primary'],
                ['target' => 'whole_milk', 'qty' => '240.0000', 'purpose' => 'component'],
            ],
            'variants' => [
                ['name' => '12oz Latte', 'sku' => 'LAT-12', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
                ['name' => '16oz Latte', 'sku' => 'LAT-16', 'price_adj' => '1.00', 'portion_mult' => '1.33'],
            ],
            'modifier_groups' => [
                [
                    'name' => 'Milk Options',
                    'required' => false,
                    'min' => 0,
                    'max' => 1,
                    'modifiers' => ['Oat Milk', 'Soy Milk'],
                ],
                [
                    'name' => 'Flavor Shots',
                    'required' => false,
                    'min' => 0,
                    'max' => 3,
                    'modifiers' => ['Vanilla Syrup', 'Caramel Syrup'],
                ],
            ],
        ],
        
        // Modifiers
        'Oat Milk' => [
            'type' => 'modifier',
            'category' => 'beverage_addon',
            'description' => 'Substitute oat milk',
            'base_price' => '0.75',
            'components' => [
                ['target' => 'oat_milk', 'qty' => '240.0000', 'purpose' => 'primary'],
            ],
            'variants' => [
                ['name' => 'Oat Milk Substitute', 'sku' => 'MOD-OAT', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
            ],
        ],
        
        'Soy Milk' => [
            'type' => 'modifier',
            'category' => 'beverage_addon',
            'description' => 'Substitute soy milk',
            'base_price' => '0.50',
            'variants' => [
                ['name' => 'Soy Milk Substitute', 'sku' => 'MOD-SOY', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
            ],
        ],
        
        'Vanilla Syrup' => [
            'type' => 'modifier',
            'category' => 'beverage_addon',
            'description' => 'Add vanilla syrup',
            'base_price' => '0.50',
            'components' => [
                ['target' => 'vanilla_syrup', 'qty' => '15.0000', 'purpose' => 'primary'],
            ],
            'variants' => [
                ['name' => 'Vanilla Syrup Shot', 'sku' => 'MOD-VAN', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
            ],
        ],
        
        'Caramel Syrup' => [
            'type' => 'modifier',
            'category' => 'beverage_addon',
            'description' => 'Add caramel syrup',
            'base_price' => '0.50',
            'components' => [
                ['target' => 'caramel_syrup', 'qty' => '15.0000', 'purpose' => 'primary'],
            ],
            'variants' => [
                ['name' => 'Caramel Syrup Shot', 'sku' => 'MOD-CAR', 'price_adj' => '0.00', 'portion_mult' => '1.00'],
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::SELLABLES as $name => $data) {
            $sellable = new Sellable();
            $sellable->setName($name);
            $sellable->setType($data['type']);
            $sellable->setCategory($data['category']);
            $sellable->setDescription($data['description']);
            $sellable->setBasePrice($data['base_price']);
            $sellable->setStatus('active');
            
            $manager->persist($sellable);
            
            // Add components
            if (isset($data['components'])) {
                foreach ($data['components'] as $compData) {
                    $targetRef = 'stock_target_' . $compData['target'];
                    if ($this->hasReference($targetRef, StockTarget::class)) {
                        $target = $this->getReference($targetRef, StockTarget::class);
                        
                        $component = new SellableComponent();
                        $component->setSellable($sellable);
                        $component->setTarget($target);
                        $component->setQuantityMultiplier($compData['qty']);
                        $component->setPurpose($compData['purpose']);
                        
                        $manager->persist($component);
                        $sellable->addComponent($component);
                    }
                }
            }
            
            // Add variants
            if (isset($data['variants'])) {
                foreach ($data['variants'] as $idx => $varData) {
                    $variant = new SellableVariant();
                    $variant->setSellable($sellable);
                    $variant->setVariantName($varData['name']);
                    $variant->setSku($varData['sku']);
                    $variant->setPriceAdjustment($varData['price_adj']);
                    $variant->setPortionMultiplier($varData['portion_mult']);
                    $variant->setSortOrder($idx);
                    $variant->setStatus('active');
                    
                    $manager->persist($variant);
                    $sellable->addVariant($variant);
                }
            }
            
            // Store reference for modifier groups
            $this->addReference('sellable_' . $this->slugify($name), $sellable);
        }
        
        $manager->flush();
        
        // Second pass: Add modifier groups (requires sellable references to exist)
        foreach (self::SELLABLES as $name => $data) {
            if (isset($data['modifier_groups'])) {
                $sellable = $this->getReference('sellable_' . $this->slugify($name), Sellable::class);
                
                foreach ($data['modifier_groups'] as $idx => $mgData) {
                    $modGroup = new SellableModifierGroup();
                    $modGroup->setSellable($sellable);
                    $modGroup->setName($mgData['name']);
                    $modGroup->setRequired($mgData['required']);
                    $modGroup->setMinSelections($mgData['min']);
                    $modGroup->setMaxSelections($mgData['max']);
                    $modGroup->setSortOrder($idx);
                    
                    // Add modifiers to the group
                    foreach ($mgData['modifiers'] as $modName) {
                        $modRef = 'sellable_' . $this->slugify($modName);
                        if ($this->hasReference($modRef, Sellable::class)) {
                            $modifier = $this->getReference($modRef, Sellable::class);
                            $modGroup->addModifier($modifier);
                        }
                    }
                    
                    $manager->persist($modGroup);
                    $sellable->addModifierGroup($modGroup);
                }
            }
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            StockTargetsFixture::class,
        ];
    }

    private function slugify(string $text): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_', $text), '_'));
    }
}
