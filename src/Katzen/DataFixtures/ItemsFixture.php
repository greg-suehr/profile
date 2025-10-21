<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Item;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ItemsFixture extends Fixture implements DependentFixtureInterface
{
    private const ITEMS = [
        // Ingredients
        ['name' => 'Greek Yogurt', 'category' => 'ingredient', 'subcategory' => 'dairy'],
        ['name' => 'Granola', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        ['name' => 'Strawberries', 'category' => 'ingredient', 'subcategory' => 'produce'],
        ['name' => 'Honey', 'category' => 'ingredient', 'subcategory' => 'sweetener'],
        ['name' => 'All-purpose Flour', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        ['name' => 'Sugar', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        ['name' => 'Unsalted Butter', 'category' => 'ingredient', 'subcategory' => 'dairy'],
        ['name' => 'Eggs', 'category' => 'ingredient', 'subcategory' => 'dairy'],
        ['name' => 'Blueberries', 'category' => 'ingredient', 'subcategory' => 'produce'],
        ['name' => 'Yeast', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        ['name' => 'Milk', 'category' => 'ingredient', 'subcategory' => 'dairy'],
        ['name' => 'Chocolate', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        ['name' => 'Salt', 'category' => 'ingredient', 'subcategory' => 'dry goods'],
        
        // Packaging
        ['name' => '12oz Clear Cup', 'category' => 'packaging', 'subcategory' => 'container'],
        ['name' => 'Dome Lid', 'category' => 'packaging', 'subcategory' => 'container'],
        ['name' => 'Muffin Liner', 'category' => 'packaging', 'subcategory' => 'baking'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ITEMS as $itemData) {
            $existing = $manager->getRepository(Item::class)
                ->findOneBy(['name' => $itemData['name']]);
            
            if ($existing) {
                $item = $existing;
            } else {
                $item = new Item();
                $item->setName($itemData['name']);
                $item->setCreatedAt(new \DateTimeImmutable());
            }
            
            $item->setCategory($itemData['category']);
            $item->setSubcategory($itemData['subcategory']);
            $item->setUpdatedAt(new \DateTime());
            
            $manager->persist($item);
            
            // Store reference for RecipesFixture
            $this->addReference('item_' . strtolower(str_replace([' ', '-'], '_', $itemData['name'])), $item);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UnitsFixture::class,
        ];
    }
}