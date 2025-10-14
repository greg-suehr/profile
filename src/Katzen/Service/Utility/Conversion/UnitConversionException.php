<?php

namespace App\Katzen\Service\Utility\Conversion;

final class UnitConversionException extends \RuntimeException
{
  private array $context;

  public function __construct(
    string $message,
    array $context = [],
    int $code = 0,
    ?\Throwable $prev = null
  )
  {
    parent::__construct($message, $code, $prev);
    $this->context = $context;
  }

  public function getContext(): array { return $this->context; }      
}
