<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\PriceRule;
use App\Katzen\Entity\Sellable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PriceRulesFixture extends Fixture implements DependentFixtureInterface
{
    private const PRICE_RULES = [
        [
            'name' => 'Happy Hour - 20% Off Beverages',
            'description' => 'Happy hour discount on all beverages between 2-5 PM',
            'type' => 'time_based',
            'priority' => 100,
            'stackable' => false,
            'exclusive' => false,
            'conditions' => [
                ['field' => 'time.hour', 'operator' => 'between', 'value' => [14, 17]],
            ],
            'actions' => [
                ['type' => 'percentage_discount', 'value' => 20],
            ],
            'applicable_categories' => ['beverage'],
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
        
        [
            'name' => 'Wholesale Customer Discount',
            'description' => 'Standard 15% discount for wholesale customers',
            'type' => 'customer_segment',
            'priority' => 90,
            'stackable' => true,
            'exclusive' => false,
            'conditions' => [
                ['field' => 'customerSegment', 'operator' => '=', 'value' => 'wholesale'],
            ],
            'actions' => [
                ['type' => 'percentage_discount', 'value' => 15],
            ],
            'applicable_categories' => null, // Applies to all
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
        
        [
            'name' => 'VIP Customer - Fixed 25% Discount',
            'description' => 'Exclusive VIP customer pricing',
            'type' => 'customer_segment',
            'priority' => 95,
            'stackable' => false,
            'exclusive' => true,
            'conditions' => [
                ['field' => 'customerSegment', 'operator' => '=', 'value' => 'vip'],
            ],
            'actions' => [
                ['type' => 'percentage_discount', 'value' => 25],
            ],
            'applicable_categories' => null,
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
        
        [
            'name' => 'Bulk Order Discount',
            'description' => '10% off orders with 10+ items',
            'type' => 'volume_tier',
            'priority' => 80,
            'stackable' => true,
            'exclusive' => false,
            'conditions' => [
                ['field' => 'quantity', 'operator' => '>=', 'value' => 10],
            ],
            'actions' => [
                ['type' => 'percentage_discount', 'value' => 10],
            ],
            'applicable_categories' => null,
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
        
        [
            'name' => 'Catering Channel - 20% Off',
            'description' => 'Special pricing for catering orders',
            'type' => 'promotion',
            'priority' => 85,
            'stackable' => false,
            'exclusive' => false,
            'conditions' => [
                ['field' => 'channel', 'operator' => '=', 'value' => 'catering'],
            ],
            'actions' => [
                ['type' => 'percentage_discount', 'value' => 20],
            ],
            'applicable_categories' => null,
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
        
        [
            'name' => 'Weekend Breakfast Special',
            'description' => '$1 off breakfast items on weekends',
            'type' => 'time_based',
            'priority' => 75,
            'stackable' => true,
            'exclusive' => false,
            'conditions' => [
                ['field' => 'time.dayOfWeek', 'operator' => 'in', 'value' => [6, 7]], // Saturday, Sunday
            ],
            'actions' => [
                ['type' => 'fixed_discount', 'value' => 1.00],
            ],
            'applicable_categories' => ['breakfast', 'bakery'],
            'valid_from' => null,
            'valid_to' => null,
            'status' => 'active',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PRICE_RULES as $ruleData) {
            $rule = new PriceRule();
            $rule->setName($ruleData['name']);
            $rule->setDescription($ruleData['description']);
            $rule->setType($ruleData['type']);
            $rule->setPriority($ruleData['priority']);
            $rule->setStackable($ruleData['stackable']);
            $rule->setExclusive($ruleData['exclusive']);
            $rule->setConditions($ruleData['conditions']);
            $rule->setActions($ruleData['actions']);
            $rule->setStatus($ruleData['status']);
            
            if ($ruleData['valid_from']) {
                $rule->setValidFrom(new \DateTime($ruleData['valid_from']));
            }
            
            if ($ruleData['valid_to']) {
                $rule->setValidTo(new \DateTime($ruleData['valid_to']));
            }
            
            // Link applicable sellables by category
            if ($ruleData['applicable_categories']) {
                foreach ($ruleData['applicable_categories'] as $category) {
                    $sellables = $manager->getRepository(Sellable::class)
                        ->findBy(['category' => $category, 'status' => 'active']);
                    
                    foreach ($sellables as $sellable) {
                        $rule->addApplicableSellable($sellable);
                    }
                }
            }
            // If no categories specified, rule applies to all sellables
            
            $manager->persist($rule);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SellablesFixture::class,
        ];
    }
}