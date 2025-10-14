<?php

namespace App\Katzen\Service\Utility\Conversion\Planner;

use App\Katzen\Entity\Unit;
use App\Katzen\Service\Utility\Conversion\{
  ConversionEdge, ConversionPlan, ConversionContext, ConversionPlannerInterface, UnitConversionException
};

final class Planner implements ConversionPlannerInterface
{
  public function plan(Unit $from, Unit $to, ?ConversionContext $ctx = null): ConversionPlan
  {
    $fromCat = $from->getCategory();
    $toCat   = $to->getCategory();
    if ($fromCat && $toCat && $fromCat !== $toCat) {
      throw new UnitConversionException('Cross-category conversion requires a context.', [
        'from_category' => $fromCat, 'to_category' => $toCat
      ]);
    }
    
    if ($from->getId() === $to->getId()) {
      return new ConversionPlan(1.0, [ new ConversionEdge($from, $to, 1.0, 'global') ]);
    }
    
    if ($from->getBaseUnitId() !== null && $to->getBaseUnitId() !== null &&
        $from->getBaseUnitId() === $to->getBaseUnitId()) {
      
      $fromFactor = (float) $from->getConversionFactor();
      $toFactor   = (float) $to->getConversionFactor();
      
      if ($fromFactor <= 0.0 || $toFactor <= 0.0) {
        throw new UnitConversionException('Invalid conversion factor on unit.', [
          'fromFactor' => $fromFactor, 'toFactor' => $toFactor
        ]);
      }
      
      $composite = $fromFactor / $toFactor;
      
      return new ConversionPlan(
        factor: $composite,
        edges: [
          new ConversionEdge($from, $from, $fromFactor, 'global', ['step' => 'to_base']),
          new ConversionEdge($to, $to, 1.0 / $toFactor, 'global', ['step' => 'from_base']),
        ],
        warning: null,
        metadata: ['mode' => 'same_base_stub']
      );
    }
    
    throw new UnitConversionException('No conversion path.', [
      'from' => $from->getAbbreviation(), 'to' => $to->getAbbreviation()
      ]);
    }
}
