<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;
use Psr\Log\LoggerInterface;

final class UnitConversionService
{
  public function __construct(
    private ConversionPlannerInterface $planner,
    private RoundingPolicyInterface $roundingPolicy,
    private ConversionLoggerInterface $auditLogger,
    private LoggerInterface $logger,
  ) {}

  public function convert(
    float $quantity,
    Unit $fromUnit,
    Unit $toUnit,
    ?ConversionContext $context = null,
    array $trigger = []
  ): ConversionResult {
      $this->logger->debug('Unit conversion requested', [
        'qty' => $quantity,
        'from' => $fromUnit->getAbbreviation() ?? $fromUnit->getName(),
        'to' => $toUnit->getAbbreviation() ?? $toUnit->getName(),
        'trigger' => $trigger,
      ]);

      $plan = $this->planner->plan($fromUnit, $toUnit, $context);
      $raw = $quantity * $plan->factor;
      $value = $this->roundingPolicy->apply($toUnit, $raw);

      $result = new ConversionResult(
        value: $value,
        fromUnit: $fromUnit,
        toUnit: $toUnit,
        factor: $plan->factor,
        path: $plan->edges,
        warning: $plan->warning
      );

      
      $ctx = [
        'item_id'     => $context?->item?->getId(),
        'temperature' => $context?->temperature,
        'density'     => $context?->density,
        'note'        => $context?->note,
      ];

      $this->auditLogger->log(
        originalValue: $quantity,
        convertedValue: $value,
        from: $fromUnit,
        to: $toUnit,
        factor: $plan->factor,
        edges: $plan->edges,
        trigger: $trigger,
        context: array_filter($ctx, fn($v) => $v !== null)
      );

      return $result;
  }

  public function canConvert(Unit $fromUnit, Unit $toUnit, ?ConversionContext $context = null): bool
  {
    try { $this->planner->plan($fromUnit, $toUnit, $context); return true; }
    catch (\Throwable) { return false; }
  }

  public function getFactor(Unit $fromUnit, Unit $toUnit, ?ConversionContext $context = null): float
  {
    return $this->planner->plan($fromUnit, $toUnit, $context)->factor;
  }
}
