<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UnitsFixture extends Fixture
{
    private const UNITS = [
        // Mass - base: gram
        ['name' => 'gram', 'abbr' => 'g', 'category' => 'mass', 'base_unit_name' => 'gram', 'factor' => '1.0000'],
        ['name' => 'kilogram', 'abbr' => 'kg', 'category' => 'mass', 'base_unit_name' => 'gram', 'factor' => '1000.0000'],
        ['name' => 'ounce', 'abbr' => 'oz', 'category' => 'mass', 'base_unit_name' => 'gram', 'factor' => '28.3495'],
        ['name' => 'pound', 'abbr' => 'lb', 'category' => 'mass', 'base_unit_name' => 'gram', 'factor' => '453.5920'],
        
        // Volume - base: milliliter
        ['name' => 'milliliter', 'abbr' => 'ml', 'category' => 'volume', 'base_unit_name' => 'milliliter', 'factor' => '1.0000'],
        ['name' => 'liter', 'abbr' => 'l', 'category' => 'volume', 'base_unit_name' => 'milliliter', 'factor' => '1000.0000'],
        
        // Count
        ['name' => 'each', 'abbr' => 'ea', 'category' => 'count', 'base_unit_name' => 'each', 'factor' => '1.0000'],
        
        // Kitchen (converted to volume)
        ['name' => 'cup', 'abbr' => 'cup', 'category' => 'kitchen', 'base_unit_name' => 'milliliter', 'factor' => '236.5880'],
        ['name' => 'tablespoon', 'abbr' => 'tbsp', 'category' => 'kitchen', 'base_unit_name' => 'milliliter', 'factor' => '14.7868'],
        ['name' => 'teaspoon', 'abbr' => 'tsp', 'category' => 'kitchen', 'base_unit_name' => 'milliliter', 'factor' => '4.9289'],
    ];

    public function load(ObjectManager $manager): void
    {
        $units = [];
        
        // First pass: create all units
        foreach (self::UNITS as $unitData) {
            $existing = $manager->getRepository(Unit::class)
                ->findOneBy(['abbreviation' => $unitData['abbr']]);
            
            if ($existing) {
                $unit = $existing;
            } else {
                $unit = new Unit();
                $unit->setAbbreviation($unitData['abbr']);
            }
            
            $unit->setName($unitData['name']);
            $unit->setCategory($unitData['category']);
            $unit->setConversionFactor($unitData['factor']);
            
            // Temporarily set base_unit_id to 0 (will update in second pass)
            $unit->setBaseUnitId(0);
            
            $manager->persist($unit);
            $units[$unitData['name']] = $unit;
        }
        
        $manager->flush();
        
        // Second pass: set correct base_unit_id references
        foreach (self::UNITS as $unitData) {
            $unit = $units[$unitData['name']];
            $baseUnit = $units[$unitData['base_unit_name']];
            $unit->setBaseUnitId($baseUnit->getId());
        }
        
        $manager->flush();
    }
}