<?php

namespace App\Katzen\Service\Utility\Conversion\Logger;

use App\Katzen\Entity\Unit;
use App\Katzen\Entity\UnitConversionLog;
use Doctrine\ORM\EntityManagerInterface;
use App\Katzen\Service\Utility\Conversion\{
  ConversionEdge, ConversionLoggerInterface
    };

/**
 * Persists a UnitConversionLog entity.
 */
final class ConversionLogger implements ConversionLoggerInterface
{
  public function __construct(private EntityManagerInterface $em) {}
  
  public function log(
    float $originalValue,
    float $convertedValue,
    Unit $from,
    Unit $to,
    float $factor,
    array $edges,
    array $trigger = [],
    array $context = []
  ): void {
        $log = new UnitConversionLog();
        $log->setOriginalValue((string)$originalValue);
        $log->setConvertedValue((string)$convertedValue);
        $log->setFromUnit($from);
        $log->setToUnit($to);
        $log->setFactorUsed((string)$factor);
        $log->setEntityType($trigger['type'] ?? null);
        $log->setEntityId(isset($trigger['id']) ? (string)$trigger['id'] : null);
        #$log->setContextData(json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $log->setContextData($context);
        $log->setConversionPath(array_map(function(ConversionEdge $e) {
            return [
                'from'     => $e->from->getAbbreviation() ?? $e->from->getName(),
                'to'       => $e->to->getAbbreviation() ?? $e->to->getName(),
                'factor'   => $e->factor,
                'type'     => $e->type,
                'metadata' => $e->metadata,
            ];
        }, $edges));

        $this->em->persist($log);
        $this->em->flush();
    }
}
