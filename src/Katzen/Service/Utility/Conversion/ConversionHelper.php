<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;
use App\Katzen\Service\Utility\Conversion\ConversionResult;
use Psr\Log\LoggerInterface;

final class ConversionHelper
{
  public function __construct(
    private UnitConversionService $svc,
    private LoggerInterface $logger
  ) {}

  /**
   * Don't use this.
   * If you're feeling lazy, it can be helpful for debugging in the UI.
   * But you shouldn't be debugging in the UI, so don't use this.
   */  
  public function dangerConvert(
    float $qty,
    Unit $from,
    Unit $to,
    ?ConversionContext $ctx,
    ?array &$errors,
    ?array $trigger = [],
  ): ConversionResult
  {
    return $this->svc->convert($qty, $from, $to, $ctx, $trigger);
  }
  
  /**
   * Convert or return null and push a human message to $errors[].
   *
   * @param list<string> $errors
   */
  public function tryConvert(
    float $qty,
    Unit $from,
    Unit $to,
    ?ConversionContext $ctx,
    array &$errors,
    ?array $trigger = [],
  ): ?float {
    try {
      $res = $this->svc->convert($qty, $from, $to, $ctx, $trigger);
      return $res->value;
      
    } catch (UnitConversionException $e) {
      $msg = $this->humanizeError($e, $from, $to);
      $errors[] = $msg;
      $this->logger->notice('Conversion failed', ['msg' => $msg, 'ctx' => $e->getContext()]);
      return null;
      
    } catch (\Throwable $e) {
      $msg = 'Unexpected conversion error.';
      $errors[] = $msg;
      $this->logger->error('Conversion crashed', ['exception' => $e]);
      return null;
      
    }
  }
  
  private function humanizeError(UnitConversionException $e, Unit $from, Unit $to): string
  {
    $a = $from->getAbbreviation() ?? $from->getName();
    $b = $to->getAbbreviation() ?? $to->getName();
    $ctx = $e->getContext();
    
    if (str_contains(strtolower($e->getMessage()), 'cross-category')) {
      return "Can't convert $a to $b without item-specific context (density).";
    }
    
    return $e->getMessage() ?: "Can’t convert $a to $b.";
  }
}
