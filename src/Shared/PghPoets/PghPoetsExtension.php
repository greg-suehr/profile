<?php

declare(strict_types=1);

namespace App\Shared\PghPoets;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PghPoetsExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pgh_prose', $this->prose(...), ['is_safe' => ['html']]),
            new TwigFilter('pgh_inline', $this->inline(...), ['is_safe' => ['html']]),
        ];
    }

    public function prose(?string $text): string
    {
        $text = trim((string) $text);
        $break = str_contains($text, "\n\n") ? '/\n\s*\n/' : '/\n/';
        $paragraphs = preg_split($break, $text) ?: [];

        return implode("\n", array_map(
            fn (string $p): string => '<p>'.$this->inline($p).'</p>',
            array_filter($paragraphs, static fn (string $p): bool => '' !== trim($p)),
        ));
    }

    public function inline(?string $text): string
    {
        $html = htmlspecialchars(trim((string) $text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\s*\n\s*/', ' ', $html) ?? $html;

        return preg_replace('/(?<![\w])_([^_]+)_(?![\w])/u', '<i>$1</i>', $html) ?? $html;
    }
}
