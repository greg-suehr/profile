<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('mixed_fraction', [$this, 'toMixedFraction']),
        ];
    }

    public function toMixedFraction(float $value): string
    {
        $whole = floor($value);
        $fraction = $value - $whole;

        $denominators = [2, 3, 4, 8, 16, 32];
        foreach ($denominators as $denom) {
            $numerator = round($fraction * $denom);
            if (abs($fraction - ($numerator / $denom)) < 0.01 && $numerator > 0) {
                if ($whole > 0) {
                    return "{$whole} {$numerator}/{$denom}";
                }
                return "{$numerator}/{$denom}";
            }
        }
        
        // Fallback for decimals outside resolution
        return rtrim(sprintf("%.2f", $value), '0.'); 
    }
}

?>
