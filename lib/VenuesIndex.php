<?php

declare(strict_types=1);

require_once __DIR__ . '/VenueUtils.php';

class VenuesIndex
{
    /**
     * @return array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>}
     */
    public static function load(string $path): array
    {
        if (!is_readable($path)) {
            return self::emptyIndex();
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in {$path}");
        }

        return self::normalizeIndex($data);
    }

    /**
     * @param list<array<string, mixed>> $weeks
     * @return array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>}
     */
    public static function updateFromWeeks(string $path, array $weeks, string $sourceFile): array
    {
        $existing = is_readable($path) ? self::load($path) : self::emptyIndex();
        $incoming = VenueUtils::venuesByNameFromWeeks($weeks);
        $merged = self::mergeVenueLists($existing['venues'], $incoming);
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format(DateTimeInterface::ATOM);

        $index = [
            'generatedAt' => $now,
            'lastSourceFile' => $sourceFile,
            'venueCount' => count($merged),
            'venues' => $merged,
        ];

        self::save($path, $index);

        return $index;
    }

    /**
     * Build a fresh index from all week files on disk (bootstrap / fallback).
     *
     * @param callable(string): array<string, mixed> $loadWeek
     * @return array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>}
     */
    public static function buildFromWeekStarts(string $weeksDir, callable $loadWeek): array
    {
        $weeks = [];

        foreach (glob($weeksDir . '/*.json') ?: [] as $file) {
            if (!preg_match('/(\d{8})\.json$/', $file, $matches)) {
                continue;
            }

            $weeks[] = $loadWeek($file);
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format(DateTimeInterface::ATOM);

        $venues = VenueUtils::venuesByNameFromWeeks($weeks);

        return [
            'generatedAt' => $now,
            'lastSourceFile' => '',
            'venueCount' => count($venues),
            'venues' => $venues,
        ];
    }

    /**
     * @param array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>} $index
     */
    public static function save(string $path, array $index): void
    {
        $normalized = self::normalizeIndex($index);
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create directory: {$dir}");
        }

        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode venues index JSON');
        }

        if (file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
    }

    /**
     * @param list<array{name: string, slugs: list<string>}> $existing
     * @param list<array{name: string, slugs: list<string>}> $incoming
     * @return list<array{name: string, slugs: list<string>}>
     */
    public static function mergeVenueLists(array $existing, array $incoming): array
    {
        $byName = [];

        foreach ($existing as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $byName[$name] = array_values(array_unique(array_map('strval', $entry['slugs'] ?? [])));
        }

        foreach ($incoming as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $slugs = array_values(array_unique(array_map('strval', $entry['slugs'] ?? [])));

            if (!isset($byName[$name])) {
                $byName[$name] = $slugs;
                continue;
            }

            $byName[$name] = array_values(array_unique(array_merge($byName[$name], $slugs)));
        }

        $venues = [];

        foreach ($byName as $name => $slugs) {
            sort($slugs);
            $venues[] = [
                'name' => $name,
                'slugs' => $slugs,
            ];
        }

        usort(
            $venues,
            static fn (array $a, array $b): int => VenueUtils::compareNames($a['name'], $b['name'])
        );

        return $venues;
    }

    /**
     * @return array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>}
     */
    private static function emptyIndex(): array
    {
        return [
            'generatedAt' => '',
            'lastSourceFile' => '',
            'venueCount' => 0,
            'venues' => [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{generatedAt: string, lastSourceFile: string, venueCount: int, venues: list<array{name: string, slugs: list<string>}>}
     */
    private static function normalizeIndex(array $data): array
    {
        $venues = [];

        foreach ($data['venues'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $slugs = array_values(array_unique(array_filter(array_map(
                static fn ($slug): string => trim((string) $slug),
                is_array($entry['slugs'] ?? null) ? $entry['slugs'] : []
            ))));
            sort($slugs);

            if ($slugs === []) {
                $slugs = [VenueUtils::slug($name)];
            }

            $venues[] = [
                'name' => $name,
                'slugs' => $slugs,
            ];
        }

        usort(
            $venues,
            static fn (array $a, array $b): int => VenueUtils::compareNames($a['name'], $b['name'])
        );

        return [
            'generatedAt' => (string) ($data['generatedAt'] ?? ''),
            'lastSourceFile' => (string) ($data['lastSourceFile'] ?? ''),
            'venueCount' => count($venues),
            'venues' => $venues,
        ];
    }
}
