<?php

namespace App\Katzen\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class DashboardLayout
{
  public function __construct(
    public string $context,
    public string $section,
    public ?string $item = null,
  ) {}
}
