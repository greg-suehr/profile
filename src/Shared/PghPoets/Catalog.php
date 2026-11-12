<?php

declare(strict_types=1);

namespace App\Shared\PghPoets;

use Symfony\Component\Yaml\Yaml;

final class Catalog
{
    public const TIE_KINDS = [
        'born'      => 'Born',
        'raised'    => 'Raised',
        'studied'   => 'Studied',
        'lived'     => 'Lived',
        'worked'    => 'Worked',
        'taught'    => 'Taught',
        'published' => 'Published',
        'edited'    => 'Edited',
    ];

    public const INCLUSION = [
        'core' => 'Included',
        'edge' => 'Edge case',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $poets = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function dir(): string
    {
        return $this->projectDir.'/content/pghpoets';
    }

    /** @return array<string, array<string, mixed>> keyed by slug, sorted by sort_name */
    public function poets(): array
    {
        if (null !== $this->poets) {
            return $this->poets;
        }

        $poets = [];
        foreach (glob($this->dir().'/poets/*.yaml') ?: [] as $file) {
            $slug = basename($file, '.yaml');
            $poet = Yaml::parseFile($file);
            if (!\is_array($poet)) {
                continue;
            }
            $poet['slug'] = $slug;
            $poet['kinds'] = $this->kindsOf($poet);
            $poets[$slug] = $poet;
        }

        uasort($poets, static fn (array $a, array $b): int => strcmp(
            (string) ($a['sort_name'] ?? $a['name']),
            (string) ($b['sort_name'] ?? $b['name']),
        ));

        return $this->poets = $poets;
    }

    /** @return array<string, mixed>|null */
    public function poet(string $slug): ?array
    {
        return $this->poets()[$slug] ?? null;
    }

    /** @return list<array<string, mixed>> newest first */
    public function news(): array
    {
        $file = $this->dir().'/news.yaml';
        if (!is_file($file)) {
            return [];
        }
        $news = Yaml::parseFile($file);

        return \is_array($news) ? array_values($news) : [];
    }

    /**
     * Problems that would put an unsourced or malformed claim on the site.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        foreach ($this->poets() as $slug => $poet) {
            $sources = $poet['sources'] ?? [];
            foreach (['name', 'summary', 'inclusion', 'ties', 'sources', 'entry'] as $field) {
                if (empty($poet[$field])) {
                    $problems[] = "$slug: missing '$field'";
                }
            }
            if (!isset(self::INCLUSION[$poet['inclusion']['status'] ?? ''])) {
                $problems[] = "$slug: inclusion.status must be one of ".implode(', ', array_keys(self::INCLUSION));
            }
            $claims = array_merge($poet['ties'] ?? [], $poet['disputes'] ?? []);
            foreach ($claims as $i => $claim) {
                if (isset($claim['kind']) && !isset(self::TIE_KINDS[$claim['kind']])) {
                    $problems[] = "$slug: claim $i has unknown kind '{$claim['kind']}'";
                }
                if (empty($claim['sources'])) {
                    $problems[] = "$slug: claim $i cites no source";
                }
                foreach ($claim['sources'] ?? [] as $key) {
                    if (!isset($sources[$key])) {
                        $problems[] = "$slug: claim $i cites unknown source '$key'";
                    }
                }
            }
            foreach (['born', 'died'] as $event) {
                $date = $poet[$event]['date'] ?? null;
                if (null !== $date && !\is_string($date)) {
                    $problems[] = "$slug: $event.date must be a quoted string";
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function kindsOf(array $poet): array
    {
        $kinds = array_unique(array_column($poet['ties'] ?? [], 'kind'));

        return array_values(array_filter(
            array_keys(self::TIE_KINDS),
            static fn (string $k): bool => \in_array($k, $kinds, true),
        ));
    }
}
