<?php

namespace App\Katzen\Service\Utility\Conversion;

use App\Katzen\Entity\Unit;

interface ConversionPlannerInterface
{
  public function plan(Unit $from, Unit $to, ?ConversionContext $ctx = null): ConversionPlan;
}
