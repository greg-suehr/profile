<?php

namespace App\Shared\Service;

class DraftContentConverter
{
    private const SUBTITLE_MAX_LENGTH = 120;
    private const SUMMARY_MAX_LENGTH = 220;

    public function toHtml(string $draft): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $draft));

        if ($normalized === '') {
            return '';
        }

        $blocks = preg_split('/\n{2,}/', $normalized) ?: [];

        $html = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $html[] = $this->renderBlock($block);
        }

        return implode("\n", $html);
    }

    public function toPlainText(string $draft): string
    {
        $stripped = $this->stripMarkdownSyntax($draft);

        return trim(preg_replace('/[ \t]+/', ' ', $stripped) ?? '');
    }

    public function guessSubtitle(string $draft): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $this->stripMarkdownSyntax($draft)) ?? '');

        if ($plain === '') {
            return '';
        }

        if (preg_match('/^(.{1,300}?[.!?])(\s|$)/', $plain, $matches) === 1) {
            $candidate = trim($matches[1]);
        } else {
            $candidate = $plain;
        }

        return $this->escapeAndTruncate($candidate, self::SUBTITLE_MAX_LENGTH);
    }

    public function guessSummary(string $draft): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $this->stripMarkdownSyntax($draft)) ?? '');

        return $this->escapeAndTruncate($plain, self::SUMMARY_MAX_LENGTH);
    }

    private function stripMarkdownSyntax(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        $normalized = preg_replace('/^#{1,6}\s+/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^[-*]\s+/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/\*\*(.+?)\*\*/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?<!\w)_(.+?)_(?!\w)/', '$1', $normalized) ?? $normalized;

        return $normalized;
    }

    private function renderBlock(string $block): string
    {
        if (preg_match('/^(#{1,6})\s+(.*)$/s', $block, $matches) === 1) {
            $level = strlen($matches[1]);

            return sprintf('<h%d>%s</h%d>', $level, $this->inline(trim($matches[2])), $level);
        }

        $lines = array_values(array_filter(
            explode("\n", $block),
            fn (string $line): bool => trim($line) !== ''
        ));

        if ($this->isBulletList($lines)) {
            return $this->renderList($lines);
        }

        if (count($lines) > 1 && !$this->isBulletLine($lines[0]) && $this->isBulletList(array_slice($lines, 1))) {
            return '<p>' . $this->inline(trim($lines[0])) . '</p>' . $this->renderList(array_slice($lines, 1));
        }

        $paragraph = implode('<br>', array_map(
            fn (string $line): string => $this->inline(trim($line)),
            $lines
        ));

        return '<p>' . $paragraph . '</p>';
    }

    /**
     * @param string[] $lines
     */
    private function renderList(array $lines): string
    {
        $items = array_map(
            fn (string $line): string => '<li>' . $this->inline(trim(preg_replace('/^[-*]\s+/', '', trim($line)) ?? '')) . '</li>',
            $lines
        );

        return '<ul>' . implode('', $items) . '</ul>';
    }

    private function isBulletLine(string $line): bool
    {
        return preg_match('/^[-*]\s+/', trim($line)) === 1;
    }

    private function isBulletList(array $lines): bool
    {
        if ($lines === []) {
            return false;
        }

        foreach ($lines as $line) {
            if (!$this->isBulletLine($line)) {
                return false;
            }
        }

        return true;
    }

    private function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            static fn (array $matches): string => sprintf('<a href="%s">%s</a>', $matches[2], $matches[1]),
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\w)_(.+?)_(?!\w)/', '<em>$1</em>', $escaped) ?? $escaped;

        return $escaped;
    }

    private function escapeAndTruncate(string $text, int $maxLength): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        if (mb_strlen($escaped) <= $maxLength) {
            return $escaped;
        }

        $truncated = mb_substr($escaped, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " ,.;:") . '…';
    }
}
