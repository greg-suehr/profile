<?php

declare(strict_types=1);

namespace Tests\Conversion;

use App\Katzen\Entity\Unit;
use App\Katzen\Service\Utility\Conversion\{
    ConversionContext,
    ConversionEdge,
    ConversionPlan,
    ConversionPlannerInterface,
    ConversionResult,
    RoundingPolicyInterface,
    UnitConversionException,
    UnitConversionService
  };
use App\Katzen\Service\Utility\Conversion\ConversionLoggerInterface;
use App\Katzen\Service\Utility\Conversion\ConversionHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UnitConversionServiceTest extends TestCase
{
  /** Returns a Unit double with desired behavior. */
  private function makeUnit(
    int $id,
      ?string $abbr,
      ?string $name,
      ?string $category,
      ?int $baseUnitId,
      ?string $factor
  ): Unit {
      $u = $this->createMock(Unit::class);
      $u->method('getId')->willReturn($id);
      $u->method('getAbbreviation')->willReturn($abbr);
      $u->method('getName')->willReturn($name);
      $u->method('getCategory')->willReturn($category);
      $u->method('getBaseUnitId')->willReturn($baseUnitId);
      $u->method('getConversionFactor')->willReturn($factor);
      return $u;
  }
  
  public function test_convert_same_base_units_logs_and_rounds(): void
    {
      $g  = $this->makeUnit(1, 'g',  'gram',  'mass', 100, '1');      // base: gram
      $kg = $this->makeUnit(2, 'kg', 'kilogram','mass',100, '1000');  // 1 kg = 1000 g

      $planner = $this->createMock(ConversionPlannerInterface::class);
      $planner->method('plan')->willReturn(new ConversionPlan(
        factor: 1.0 / 1000.0,
        edges: [
          new ConversionEdge($g,  $g,  1.0,       'global', ['step' => 'to_base']),
          new ConversionEdge($kg, $kg, 1.0/1000., 'global', ['step' => 'from_base']),
        ],
        warning: null,
        metadata: ['mode' => 'same_base_stub']
      ));

      // Default rounding to 3 places
      $rounding = new class implements RoundingPolicyInterface {
        public function apply(Unit $to, float $value): float { return round($value, 3); }
      };
      
      $audit = $this->createMock(ConversionLoggerInterface::class);
      $audit->expects($this->once())->method('log')
            ->with(
              $this->identicalTo(1500.0),            // original
              $this->identicalTo(1.5),               // rounded result
              $this->identicalTo($g),
              $this->identicalTo($kg),
              $this->equalToWithDelta(0.001, 1e-12), // factor
              $this->callback(fn($edges) => \is_array($edges) && \count($edges) === 2),
              $this->isType('array'),
              $this->isType('array'),
            );
      
      $svc = new UnitConversionService($planner, $rounding, $audit, new NullLogger());
      
      
      $res = $svc->convert(
        quantity: 1500.0,
        fromUnit: $g,
        toUnit:   $kg,
        context:  new ConversionContext(note: 'test'),
        trigger:  ['type' => 'recipe', 'id' => 42]
      );
      

      $this->assertSame(1.5, $res->value);
      $this->assertSame($g,  $res->fromUnit);
      $this->assertSame($kg, $res->toUnit);
      $this->assertEqualsWithDelta(0.001, $res->factor, 1e-12);
      $this->assertCount(2, $res->path);
      $this->assertNull($res->warning);
    }

    public function test_tryConvert_returns_null_and_collects_user_message_on_error(): void
    {   
        $from = $this->makeUnit(10, 'ml', 'milliliter', 'volume', 200, '1');
        $to   = $this->makeUnit(20, 'g',  'gram',       'mass',   300, '1');

        $planner = $this->createMock(ConversionPlannerInterface::class);
        $planner->method('plan')->willThrowException(
            new UnitConversionException('Cross-category conversion requires a context.')
        );

        $rounding = new class implements RoundingPolicyInterface {
          public function apply(Unit $to, float $value): float { return $value; }
        };
        
        $audit = $this->createMock(ConversionLoggerInterface::class);
        $audit->expects($this->never())->method('log');
        
        $svc     = new UnitConversionService($planner, $rounding, $audit, new NullLogger());
        $helper  = new ConversionHelper($svc, new NullLogger());        
        $errors  = [];
        
        $val = $helper->tryConvert(
          qty: 100.0,
          from: $from,
          to: $to,
          ctx: null,
          errors: $errors,
          trigger: ['type' => 'recipe', 'id' => 7]
        );
        
        $this->assertNull($val);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("Can't convert ml to g without item-specific context", $errors[0]);
    }
}
