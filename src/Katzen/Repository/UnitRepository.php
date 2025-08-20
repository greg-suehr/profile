<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Unit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unit>
 */
class UnitRepository extends ServiceEntityRepository
{
  private const ALIASES = [
        'tsp' => 'tsp', 'ts' => 'tsp', 't' => 'tsp', 'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'tbsp' => 'tbsp','tbs'=>'tbsp','tbl'=>'tbsp','tablespoon'=>'tbsp','tablespoons'=>'tbsp',
        'c' => 'cup', 'cup' => 'cup', 'cups' => 'cup',
        'floz' => 'floz', 'fluidounce'=>'floz','fluidounces'=>'floz','flounce'=>'floz','fl oz'=>'floz',
        'oz'=>'oz','ounce'=>'oz','ounces'=>'oz',
        'lb'=>'lb','lbs'=>'lb','pound'=>'lb','pounds'=>'lb',
        'g'=>'g','gram'=>'g','grams'=>'g',
        'kg'=>'kg','kilogram'=>'kg','kilograms'=>'kg',
        'ml'=>'ml','milliliter'=>'ml','milliliters'=>'ml','millilitre'=>'ml','millilitres'=>'ml',
        'l'=>'l','liter'=>'l','liters'=>'l','litre'=>'l','litres'=>'l',
        'pt'=>'pt','pint'=>'pt','pints'=>'pt',
        'qt'=>'qt','quart'=>'qt','quarts'=>'qt',
        'gal'=>'gal','gallon'=>'gal','gallons'=>'gal',
        'ea'=>'ea','each'=>'ea',
        'pc'=>'pc','piece'=>'pc','pieces'=>'pc',
        'dz'=>'dz','dozen'=>'dz',
        'pinch'=>'pinch','dash'=>'dash','to taste'=>'to taste',
  ];
  
  public function __construct(ManagerRegistry $registry)
  {
      parent::__construct($registry, Unit::class);
  }
  
  /**
   * Resolve a user token (name/alias/abbr) to a Unit by matching:
   *  1) normalized alias -> canonical abbreviation
   *  2) abbreviation (case-insensitive)
   *  3) name (case-insensitive)
   */
  public function findOneByCodeOrSynonym(string $raw): ?Unit
  {
      $token = $this->normalize($raw);
      if ($token === '') {
        return null;
      }
      
      $canon = self::ALIASES[$token] ?? $token;
      
      $qb = $this->createQueryBuilder('u')
            ->where('u.abbreviation = :abbr')
            ->orWhere('u.name = :name')
            ->setParameter('abbr', $canon)
            ->setParameter('name', $canon)
            ->setMaxResults(1);
      
      return $qb->getQuery()->getOneOrNullResult();
  }

  private function normalize(string $s): string
  {
      $s = mb_strtolower(trim($s));
        
      $s = preg_replace('/[.\s]+/', '', $s);
      return $s;
  }
}
