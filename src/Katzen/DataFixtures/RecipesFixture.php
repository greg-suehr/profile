<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\KatzenUser;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RecipesFixture extends Fixture implements DependentFixtureInterface
{
    private const RECIPES = [
        'Fruit Parfait' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '5.00',
            'cook_time' => '0.00',
            'wait_time' => '0.00',
            'summary' => 'Fresh yogurt parfait with granola and strawberries',
            'ingredients' => [
                ['item' => 'greek_yogurt', 'qty' => '200', 'unit' => 'g'],
                ['item' => 'granola', 'qty' => '50', 'unit' => 'g'],
                ['item' => 'strawberries', 'qty' => '60', 'unit' => 'g'],
                ['item' => 'honey', 'qty' => '10', 'unit' => 'g'],
                ['item' => '12oz_clear_cup', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'dome_lid', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Blueberry Muffin' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '10.00',
            'cook_time' => '20.00',
            'wait_time' => '5.00',
            'summary' => 'Single serving blueberry muffin',
            'ingredients' => [
                ['item' => 'all_purpose_flour', 'qty' => '80', 'unit' => 'g'],
                ['item' => 'sugar', 'qty' => '40', 'unit' => 'g'],
                ['item' => 'unsalted_butter', 'qty' => '30', 'unit' => 'g'],
                ['item' => 'eggs', 'qty' => '1', 'unit' => 'ea'],
                ['item' => 'blueberries', 'qty' => '50', 'unit' => 'g'],
                ['item' => 'yeast', 'qty' => '1', 'unit' => 'g'],
                ['item' => 'salt', 'qty' => '1', 'unit' => 'g'],
                ['item' => 'milk', 'qty' => '10', 'unit' => 'ml'],
                ['item' => 'muffin_liner', 'qty' => '1', 'unit' => 'ea'],
            ],
        ],
        'Chocolate Croissant' => [
            'serving_min' => 1,
            'serving_max' => 1,
            'serving_unit' => 'ea',
            'prep_time' => '15.00',
            'cook_time' => '18.00',
            'wait_time' => '30.00',
            'summary' => 'Simplified chocolate croissant',
            'ingredients' => [
                ['item' => 'all_purpose_flour', 'qty' => '80', 'unit' => 'g'],
                ['item' => 'unsalted_butter', 'qty' => '15', 'unit' => 'g'],
                ['item' => 'chocolate', 'qty' => '30', 'unit' => 'g'],
                ['item' => 'yeast', 'qty' => '1', 'unit' => 'g'],
                ['item' => 'salt', 'qty' => '1', 'unit' => 'g'],
                ['item' => 'milk', 'qty' => '10', 'unit' => 'ml'],
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $author = $this->getReference(KatzenUserFixture::ADMIN_USER_REFERENCE, KatzenUser::class);
        
        foreach (self::RECIPES as $title => $recipeData) {
            $existing = $manager->getRepository(Recipe::class)
                ->findOneBy(['title' => $title]);
            
            if ($existing) {
                $recipe = $existing;
                // Clear existing ingredients to rebuild them
                foreach ($recipe->getRecipeIngredients() as $ingredient) {
                    $recipe->removeRecipeIngredient($ingredient);
                }
            } else {
                $recipe = new Recipe();
                $recipe->setTitle($title);
                $recipe->setCreatedAt(new \DateTimeImmutable());
            }
            
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
            
            // Store reference for other fixtures
            $recipeRef = strtolower(str_replace(' ', '_', $title));
            $this->addReference('recipe_' . $recipeRef, $recipe);
        }
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            KatzenUserFixture::class,
            ItemsFixture::class,
        ];
    }
}
