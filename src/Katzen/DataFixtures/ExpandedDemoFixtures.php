<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Item;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Vendor;
use App\Katzen\Entity\Unit;
use App\Katzen\Entity\KatzenUser;
use App\Katzen\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExpandedDemoFixtures extends Fixture implements DependentFixtureInterface
{
    // ========== NEW COFFEE SHOP ITEMS ==========
    private const NEW_ITEMS = [
        // Coffee & Espresso Base Items
        [
            'name' => 'Espresso Coffee Beans',
            'ref' => 'espresso_beans',
            'category' => 'ingredient',
            'base_unit' => 'g',
            'description' => 'Premium dark roast espresso beans',
        ],
        [
            'name' => 'Drip Coffee Beans',
            'ref' => 'drip_coffee_beans',
            'category' => 'ingredient',
            'base_unit' => 'g',
            'description' => 'Medium roast coffee beans for drip brewing',
        ],
        [
            'name' => 'Whole Milk',
            'ref' => 'whole_milk',
            'category' => 'ingredient',
            'base_unit' => 'ml',
            'description' => 'Full-fat dairy milk',
        ],
        [
            'name' => 'Oat Milk',
            'ref' => 'oat_milk',
            'category' => 'ingredient',
            'base_unit' => 'ml',
            'description' => 'Non-dairy oat milk alternative',
        ],
        [
            'name' => 'Heavy Cream',
            'ref' => 'heavy_cream',
            'category' => 'ingredient',
            'base_unit' => 'ml',
            'description' => 'Whipping cream for beverages',
        ],
        [
            'name' => 'Vanilla Syrup',
            'ref' => 'vanilla_syrup',
            'category' => 'ingredient',
            'base_unit' => 'ml',
            'description' => 'Flavored syrup for coffee drinks',
        ],
        [
            'name' => 'Caramel Syrup',
            'ref' => 'caramel_syrup',
            'category' => 'ingredient',
            'base_unit' => 'ml',
            'description' => 'Caramel flavored syrup',
        ],
        [
            'name' => 'Cocoa Powder',
            'ref' => 'cocoa_powder',
            'category' => 'ingredient',
            'base_unit' => 'g',
            'description' => 'Unsweetened cocoa powder for mochas',
        ],
        [
            'name' => 'Cinnamon',
            'ref' => 'cinnamon',
            'category' => 'ingredient',
            'base_unit' => 'g',
            'description' => 'Ground cinnamon spice',
        ],
        [
            'name' => 'Ice',
            'ref' => 'ice',
            'category' => 'ingredient',
            'base_unit' => 'g',
            'description' => 'Ice cubes for cold beverages',
        ],
        
        // Packaging for Coffee Drinks
        [
            'name' => '8oz Paper Cup',
            'ref' => '8oz_paper_cup',
            'category' => 'packaging',
            'base_unit' => 'ea',
            'description' => 'Small hot beverage cup',
        ],
        [
            'name' => '12oz Paper Cup',
            'ref' => '12oz_paper_cup',
            'category' => 'packaging',
            'base_unit' => 'ea',
            'description' => 'Medium hot beverage cup',
        ],
        [
            'name' => '16oz Paper Cup',
            'ref' => '16oz_paper_cup',
            'category' => 'packaging',
            'base_unit' => 'ea',
            'description' => 'Large hot beverage cup',
        ],
        [
            'name' => 'Cup Sleeve',
            'ref' => 'cup_sleeve',
            'category' => 'packaging',
            'base_unit' => 'ea',
            'description' => 'Insulating sleeve for hot cups',
        ],
        [
            'name' => 'Plastic Lid',
            'ref' => 'plastic_lid',
            'category' => 'packaging',
            'base_unit' => 'ea',
            'description' => 'Lid for paper cups',
        ],
    ];

    // ========== NEW STOCK TARGETS ==========
    private const NEW_STOCK_TARGETS = [
        ['item' => 'espresso_beans', 'unit' => 'g', 'reorder_point' => '2000.00'],
        ['item' => 'drip_coffee_beans', 'unit' => 'g', 'reorder_point' => '3000.00'],
        ['item' => 'whole_milk', 'unit' => 'ml', 'reorder_point' => '2000.00'],
        ['item' => 'oat_milk', 'unit' => 'ml', 'reorder_point' => '1000.00'],
        ['item' => 'heavy_cream', 'unit' => 'ml', 'reorder_point' => '500.00'],
        ['item' => 'vanilla_syrup', 'unit' => 'ml', 'reorder_point' => '500.00'],
        ['item' => 'caramel_syrup', 'unit' => 'ml', 'reorder_point' => '500.00'],
        ['item' => 'cocoa_powder', 'unit' => 'g', 'reorder_point' => '300.00'],
        ['item' => 'cinnamon', 'unit' => 'g', 'reorder_point' => '100.00'],
        ['item' => 'ice', 'unit' => 'g', 'reorder_point' => '5000.00'],
        ['item' => '8oz_paper_cup', 'unit' => 'ea', 'reorder_point' => '50.00'],
        ['item' => '12oz_paper_cup', 'unit' => 'ea', 'reorder_point' => '100.00'],
        ['item' => '16oz_paper_cup', 'unit' => 'ea', 'reorder_point' => '100.00'],
        ['item' => 'cup_sleeve', 'unit' => 'ea', 'reorder_point' => '100.00'],
        ['item' => 'plastic_lid', 'unit' => 'ea', 'reorder_point' => '150.00'],
    ];

    // ========== NEW COFFEE RECIPES ==========
    private const NEW_RECIPES = [
        'Espresso' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '1.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Single shot of espresso',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => '8oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Americano' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '2.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Espresso with hot water',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Cappuccino' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Espresso with steamed milk and foam',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '180', 'unit' => 'ml'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Latte' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Espresso with steamed milk',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '240', 'unit' => 'ml'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Vanilla Latte' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Latte with vanilla syrup',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '240', 'unit' => 'ml'],
                ['item' => 'vanilla_syrup', 'qty' => '20', 'unit' => 'ml'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Caramel Latte' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Latte with caramel syrup',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '240', 'unit' => 'ml'],
                ['item' => 'caramel_syrup', 'qty' => '20', 'unit' => 'ml'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Mocha' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.50',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Chocolate espresso drink with milk',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '240', 'unit' => 'ml'],
                ['item' => 'cocoa_powder', 'qty' => '15', 'unit' => 'g'],
                ['item' => 'chocolate', 'qty' => '10', 'unit' => 'g'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Iced Latte' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '2.50',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Chilled espresso with milk over ice',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '200', 'unit' => 'ml'],
                ['item' => 'ice', 'qty' => '150', 'unit' => 'g'],
                ['item' => '16oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Iced Mocha' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '3.00',
            'cook_time' => '0.50',
            'wait_time' => '0.00',
            'summary' => 'Chilled chocolate espresso drink',
            'ingredients' => [
                ['item' => 'espresso_beans', 'qty' => '18', 'unit' => 'g'],
                ['item' => 'whole_milk', 'qty' => '200', 'unit' => 'ml'],
                ['item' => 'cocoa_powder', 'qty' => '15', 'unit' => 'g'],
                ['item' => 'chocolate', 'qty' => '10', 'unit' => 'g'],
                ['item' => 'ice', 'qty' => '150', 'unit' => 'g'],
                ['item' => '16oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Drip Coffee - Small' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '0.50',
            'cook_time' => '5.00',
            'wait_time' => '0.00',
            'summary' => 'Small brewed drip coffee',
            'ingredients' => [
                ['item' => 'drip_coffee_beans', 'qty' => '15', 'unit' => 'g'],
                ['item' => '12oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Drip Coffee - Large' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '0.50',
            'cook_time' => '5.00',
            'wait_time' => '0.00',
            'summary' => 'Large brewed drip coffee',
            'ingredients' => [
                ['item' => 'drip_coffee_beans', 'qty' => '22', 'unit' => 'g'],
                ['item' => '16oz_paper_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'plastic_lid', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'cup_sleeve', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
    ];

    // ========== CUSTOMERS ==========
    private const CUSTOMERS = [
        [
            'name' => 'Sarah Mitchell',
            'email' => 'sarah.mitchell@email.com',
            'phone' => '412-555-0123',
            'type' => 'individual',
            'billing_address' => '123 Main Street, Pittsburgh, PA 15213',
            'shipping_address' => '123 Main Street, Pittsburgh, PA 15213',
            'notes' => 'Regular customer, prefers oat milk',
        ],
        [
            'name' => 'David Chen',
            'email' => 'david.chen@email.com',
            'phone' => '412-555-0456',
            'type' => 'individual',
            'billing_address' => '456 Forbes Avenue, Pittsburgh, PA 15213',
            'shipping_address' => '456 Forbes Avenue, Pittsburgh, PA 15213',
            'notes' => 'Likes extra hot drinks',
        ],
        [
            'name' => 'TechStart Inc',
            'email' => 'orders@techstart.com',
            'phone' => '412-555-0789',
            'type' => 'business',
            'billing_address' => '789 Liberty Avenue, Pittsburgh, PA 15222',
            'shipping_address' => '789 Liberty Avenue, Suite 500, Pittsburgh, PA 15222',
            'notes' => 'Weekly office orders, 20+ drinks. Contact: Jennifer',
        ],
        [
            'name' => 'Emily Rodriguez',
            'email' => 'emily.r@email.com',
            'phone' => '412-555-0234',
            'type' => 'individual',
            'billing_address' => '234 Oakland Avenue, Pittsburgh, PA 15213',
            'shipping_address' => '234 Oakland Avenue, Pittsburgh, PA 15213',
            'notes' => 'Rewards member since 2023',
        ],
        [
            'name' => 'Green Valley Cafe',
            'email' => 'wholesale@greenvalley.com',
            'phone' => '412-555-0567',
            'type' => 'wholesale',
            'billing_address' => '567 Penn Avenue, Pittsburgh, PA 15222',
            'shipping_address' => '567 Penn Avenue, Pittsburgh, PA 15222',
            'notes' => 'Wholesale customer, buys pastries for resale',
        ],
    ];

    // ========== VENDORS ==========
    private const VENDORS = [
        [
            'name' => 'Premium Coffee Roasters',
            'email' => 'sales@premiumcoffee.com',
            'phone' => '800-555-0100',
            'type' => 'supplier',
            'billing_address' => '1000 Roaster Way, Seattle, WA 98101',
            'shipping_address' => '1000 Roaster Way, Seattle, WA 98101',
            'notes' => 'Primary coffee bean supplier, weekly deliveries',
            'payment_terms' => 'Net 30',
            'tax_id' => '12-3456789',
        ],
        [
            'name' => 'Dairy Fresh Distributors',
            'email' => 'orders@dairyfresh.com',
            'phone' => '412-555-0800',
            'type' => 'supplier',
            'billing_address' => '2500 Milk Road, Pittsburgh, PA 15220',
            'shipping_address' => '2500 Milk Road, Pittsburgh, PA 15220',
            'notes' => 'Dairy and alternative milk supplier, twice weekly delivery',
            'payment_terms' => 'Net 15',
            'tax_id' => '23-4567890',
        ],
        [
            'name' => 'Sweet Supplies Co',
            'email' => 'info@sweetsupplies.com',
            'phone' => '412-555-0900',
            'type' => 'supplier',
            'billing_address' => '3000 Sugar Lane, Pittsburgh, PA 15212',
            'shipping_address' => '3000 Sugar Lane, Pittsburgh, PA 15212',
            'notes' => 'Syrups, chocolate, and baking supplies',
            'payment_terms' => 'Net 30',
            'tax_id' => '34-5678901',
        ],
        [
            'name' => 'EcoPack Solutions',
            'email' => 'sales@ecopack.com',
            'phone' => '800-555-0200',
            'type' => 'supplier',
            'billing_address' => '4000 Green Street, Portland, OR 97201',
            'shipping_address' => '4000 Green Street, Portland, OR 97201',
            'notes' => 'Sustainable packaging supplier, monthly bulk orders',
            'payment_terms' => 'Net 45',
            'tax_id' => '45-6789012',
        ],
        [
            'name' => 'Local Flour Mill',
            'email' => 'orders@localflourmill.com',
            'phone' => '412-555-1000',
            'type' => 'supplier',
            'billing_address' => '5000 Mill Road, Pittsburgh, PA 15235',
            'shipping_address' => '5000 Mill Road, Pittsburgh, PA 15235',
            'notes' => 'Local supplier for flour and baking ingredients',
            'payment_terms' => 'Net 30',
            'tax_id' => '56-7890123',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $author = $this->getReference(KatzenUserFixture::ADMIN_USER_REFERENCE, KatzenUser::class);
        
        // ========== CREATE NEW ITEMS ==========
        foreach (self::NEW_ITEMS as $itemData) {
            $existing = $manager->getRepository(Item::class)
                ->findOneBy(['name' => $itemData['name']]);
            
            if (!$existing) {
                $item = new Item();
                $item->setName($itemData['name']);
                $item->setCategory($itemData['category']);
                $item->setDescription($itemData['description']);
                
                $baseUnit = $manager->getRepository(Unit::class)
                    ->findOneBy(['abbreviation' => $itemData['base_unit']]);
                
                $item->setCreatedAt(new \DateTimeImmutable());
                $item->setUpdatedAt(new \DateTime());
                
                $manager->persist($item);
                $this->addReference('item_' . $itemData['ref'], $item);
            } else {
                $this->addReference('item_' . $itemData['ref'], $existing);
            }
        }
        
        $manager->flush();

        // ========== CREATE NEW STOCK TARGETS ==========
        foreach (self::NEW_STOCK_TARGETS as $targetData) {
            $item = $this->getReference('item_' . $targetData['item'], Item::class);
            
            $existing = $manager->getRepository(StockTarget::class)
                ->findOneBy(['item' => $item]);
            
            if (!$existing) {
                $stockTarget = new StockTarget();
                $stockTarget->setItem($item);
                $stockTarget->setName($item->getName());
                
                $baseUnit = $manager->getRepository(Unit::class)
                    ->findOneBy(['abbreviation' => $targetData['unit']]);
                $stockTarget->setBaseUnit($baseUnit);
                
                $stockTarget->setCurrentQty('0.00');
                $stockTarget->setReorderPoint($targetData['reorder_point']);
                $stockTarget->setStatus('active');
                
                $manager->persist($stockTarget);
            }
        }
        
        $manager->flush();

        // ========== CREATE NEW RECIPES ==========
        foreach (self::NEW_RECIPES as $title => $recipeData) {
            $existing = $manager->getRepository(Recipe::class)
                ->findOneBy(['title' => $title]);
            
            if (!$existing) {
                $recipe = new Recipe();
                $recipe->setTitle($title);
                $recipe->setAuthor($author);
                $recipe->setSummary($recipeData['summary']);
                $recipe->setServingMinQty($recipeData['serving_min']);
                $recipe->setServingMaxQty($recipeData['serving_max']);
                
                $servingUnit = $manager->getRepository(Unit::class)
                    ->findOneBy(['abbreviation' => $recipeData['serving_unit']]);
                $recipe->setServingUnit($servingUnit);
                
                $recipe->setPrepTime($recipeData['prep_time']);
                $recipe->setCookTime($recipeData['cook_time']);
                $recipe->setWaitTime($recipeData['wait_time']);
                $recipe->setCreatedAt(new \DateTimeImmutable());
                $recipe->setUpdatedAt(new \DateTime());
                $recipe->setVersion(1);
                $recipe->setStatus('active');
                $recipe->setIsPublic(true);
                
                $manager->persist($recipe);
                
                // Add ingredients
                foreach ($recipeData['ingredients'] as $ingredientData) {
                    $item = $this->getReference('item_' . $ingredientData['item'], Item::class);
                    $unit = $manager->getRepository(Unit::class)
                        ->findOneBy(['abbreviation' => $ingredientData['unit']]);
                    
                    $ingredient = new RecipeIngredient();
                    $ingredient->setRecipe($recipe);
                    $ingredient->setSupplyType('item');
                    $ingredient->setSupplyId($item->getId());
                    $ingredient->setQuantity($ingredientData['qty']);
                    $ingredient->setUnit($unit);
                    
                    $recipe->addRecipeIngredient($ingredient);
                    $manager->persist($ingredient);
                }
                
                // Store reference
                $recipeRef = strtolower(str_replace([' ', '-'], '_', $title));
                $this->addReference('recipe_' . $recipeRef, $recipe);
            }
        }
        
        $manager->flush();

        // ========== CREATE ACTIVE MENU ==========
        $existingMenu = $manager->getRepository(RecipeList::class)
            ->findOneBy(['name' => 'Menu']);
        
        if (!$existingMenu) {
            $menu = new RecipeList();
            $menu->setName('Menu');
            $menu->setCreatedAt(new \DateTimeImmutable());
            $menu->setUpdatedAt(new \DateTime());
            
            // Add all recipes to the menu
            $allRecipes = [];
            foreach (self::NEW_RECIPES as $title => $data) {
                $recipeRef = strtolower(str_replace([' ', '-'], '_', $title));
                $allRecipes[] = $this->getReference('recipe_' . $recipeRef, Recipe::class);
            }
            
            // Also add existing pastry recipes if they exist
            $existingPastries = ['Yogurt Parfait', 'Blueberry Muffin', 'Pain au Chocolat'];
            foreach ($existingPastries as $title) {
                try {
                    $recipe = $manager->getRepository(Recipe::class)->findOneBy(['title' => $title]);
                    if ($recipe) {
                        $allRecipes[] = $recipe;
                    }
                } catch (\Exception $e) {
                    // Recipe doesn't exist, skip it
                }
            }
            
            foreach ($allRecipes as $recipe) {
                $menu->addRecipe($recipe);
            }
            
            $manager->persist($menu);
            $manager->flush();
            
            // Create tags for active menu
            $statusTag = new Tag();
            $statusTag->setObj('recipe_list');
            $statusTag->setObjId($menu->getId());
            $statusTag->setType('status');
            $statusTag->setValue('active');
            $statusTag->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($statusTag);
            
            $menuTag = new Tag();
            $menuTag->setObj('recipe_list');
            $menuTag->setObjId($menu->getId());
            $menuTag->setType('menu');
            $menuTag->setValue('current');
            $menuTag->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($menuTag);
            
            $mealTypeTag = new Tag();
            $mealTypeTag->setObj('recipe_list');
            $mealTypeTag->setObjId($menu->getId());
            $mealTypeTag->setType('meal_type');
            $mealTypeTag->setValue('all_day');
            $mealTypeTag->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($mealTypeTag);
            
            $manager->flush();
        }

        // ========== CREATE CUSTOMERS ==========
        foreach (self::CUSTOMERS as $customerData) {
            $existing = $manager->getRepository(Customer::class)
                ->findOneBy(['email' => $customerData['email']]);
            
            if (!$existing) {
                $customer = new Customer();
                $customer->setName($customerData['name']);
                $customer->setEmail($customerData['email']);
                $customer->setPhone($customerData['phone']);
                $customer->setType($customerData['type']);
                $customer->setBillingAddress($customerData['billing_address']);
                $customer->setShippingAddress($customerData['shipping_address']);
                $customer->setNotes($customerData['notes']);
                $customer->setStatus('active');
                $customer->setAccountBalance('0.00');
                $customer->setArBalance('0.00');
                $customer->setPaymentTerms('Net 15');
                // Note: created_at and updated_at are set automatically via PrePersist/PreUpdate callbacks
                
                $manager->persist($customer);
            }
        }
        
        $manager->flush();

        // ========== CREATE VENDORS ==========
        // Note: The Vendor entity currently only has an 'id' field
        // Creating simple vendor records for future expansion
        foreach (self::VENDORS as $vendorData) {
            $vendor = new Vendor();
            $vendor->setVendorCode($vendorData['tax_id']);
            $vendor->setName($vendorData['name']);
            $vendor->setEmail($vendorData['email']);
            $vendor->setPhone($vendorData['phone']);
            $vendor->setBillingAddress($vendorData['billing_address']);
            $vendor->setShippingAddress($vendorData['shipping_address']);
            $vendor->setNotes($vendorData['notes']);
            $vendor->setStatus('active');
            $vendor->setCurrentBalance('0.00');
            $vendor->setPaymentTerms('Net 15');
            $manager->persist($vendor);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            KatzenUserFixture::class,
            ItemsFixture::class,
            UnitsFixture::class,
        ];
    }
}
