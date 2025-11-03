<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\CustomerPriceOverride;
use App\Katzen\Entity\Sellable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerPriceOverridesFixture extends Fixture implements DependentFixtureInterface
{
    private const OVERRIDES = [
        [
            'customer_name' => 'Green Valley Cafe',
            'sellable_name' => 'Espresso',
            'override_price' => '2.25', // Special wholesale price
            'valid_from' => '-30 days',
            'valid_to' => '+365 days',
            'notes' => 'Annual wholesale contract pricing',
        ],
        
        [
            'customer_name' => 'Green Valley Cafe',
            'sellable_name' => 'Latte',
            'override_price' => '3.50', // Special wholesale price
            'valid_from' => '-30 days',
            'valid_to' => '+365 days',
            'notes' => 'Annual wholesale contract pricing',
        ],
        
        [
            'customer_name' => 'Green Valley Cafe',
            'sellable_name' => 'Blueberry Muffin',
            'override_price' => '2.50', // Bulk discount
            'valid_from' => 'now',
            'valid_to' => '+180 days',
            'notes' => 'Bulk purchase agreement - 50+ muffins per week',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::OVERRIDES as $overrideData) {
            $customer = $manager->getRepository(Customer::class)
                ->findOneBy(['name' => $overrideData['customer_name']]);
            
            if (!$customer) {
                continue;
            }
            
            $sellable = $manager->getRepository(Sellable::class)
                ->findOneBy(['name' => $overrideData['sellable_name']]);
            
            if (!$sellable) {
                continue;
            }
            
            $override = new CustomerPriceOverride();
            $override->setCustomer($customer);
            $override->setSellable($sellable);
            $override->setOverridePrice($overrideData['override_price']);
            $override->setValidFrom(new \DateTime($overrideData['valid_from']));
            
            if ($overrideData['valid_to']) {
                $override->setValidTo(new \DateTime($overrideData['valid_to']));
            }
            
            $override->setNotes($overrideData['notes']);
            
            $manager->persist($override);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ExpandedDemoFixtures::class,
            SellablesFixture::class,
        ];
    }
}
