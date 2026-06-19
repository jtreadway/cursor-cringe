<?php

declare(strict_types=1);

require_once __DIR__ . '/Calendar.php';

class CalendarLinter
{
    /**
     * @return list<array{code: string, message: string}>
     */
    public static function lintFile(string $sourcePath): array
    {
        $calendar = new Calendar($sourcePath);

        return self::lintListings($calendar->getParsedListings());
    }

    /**
     * @param list<array{venue: array<string, string>, events?: list<array<string, string>>}> $listings
     * @return list<array{code: string, message: string}>
     */
    public static function lintListings(array $listings): array
    {
        $warnings = [];
        $seen = [];

        foreach ($listings as $listing) {
            $venue = $listing['venue'];
            $venueName = trim((string) ($venue['name'] ?? ''));
            if ($venueName === '') {
                continue;
            }

            foreach ($listing['events'] ?? [] as $event) {
                $date = trim((string) ($event['date'] ?? ''));
                $label = self::eventLabel($event);
                if ($date === '' || $label === '') {
                    continue;
                }

                $key = $date . "\0" . self::normalize($label);
                if (!isset($seen[$key])) {
                    $seen[$key] = [];
                }

                $seen[$key][$venueName] = true;
            }
        }

        foreach ($seen as $key => $venues) {
            $venueNames = array_keys($venues);
            if (count($venueNames) < 2) {
                continue;
            }

            [$date, $label] = explode("\0", $key, 2);
            if (!self::shouldWarnDuplicateEvent($label, $venueNames)) {
                continue;
            }

            sort($venueNames);
            $warnings[] = [
                'code' => 'duplicate_event',
                'message' => sprintf(
                    '%s: "%s" is listed under multiple venues: %s',
                    $date,
                    $label,
                    self::quotedList($venueNames)
                ),
            ];
        }

        $eventTitlesByVenue = [];
        $venueMeta = [];

        foreach ($listings as $listing) {
            $venue = $listing['venue'];
            $venueName = trim((string) ($venue['name'] ?? ''));
            if ($venueName === '') {
                continue;
            }

            $venueMeta[$venueName] = [
                'hasContact' => !empty($venue['url']) || !empty($venue['phone']),
            ];

            foreach ($listing['events'] ?? [] as $event) {
                $label = self::eventLabel($event);
                if ($label === '') {
                    continue;
                }

                $eventTitlesByVenue[$venueName][] = $label;
            }
        }

        foreach ($listings as $listing) {
            $venue = $listing['venue'];
            $venueName = trim((string) ($venue['name'] ?? ''));
            if ($venueName === '') {
                continue;
            }

            $normalizedVenue = self::normalize($venueName);

            foreach ($eventTitlesByVenue as $otherVenue => $titles) {
                if ($otherVenue === $venueName) {
                    continue;
                }

                foreach ($titles as $title) {
                    if ($normalizedVenue !== self::normalize($title)) {
                        continue;
                    }

                    $warnings[] = [
                        'code' => 'venue_matches_event_title',
                        'message' => sprintf(
                            'Venue name %s matches an event title under %s (%s)',
                            self::quote($venueName),
                            self::quote($otherVenue),
                            self::quote($title)
                        ),
                    ];
                    break 2;
                }
            }
        }

        $venueNames = array_keys($venueMeta);
        usort($venueNames, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($listings as $listing) {
            $venue = $listing['venue'];
            $venueName = trim((string) ($venue['name'] ?? ''));
            if ($venueName === '') {
                continue;
            }

            $hasContact = $venueMeta[$venueName]['hasContact'] ?? false;

            foreach ($venueNames as $otherVenue) {
                if ($otherVenue === $venueName || strlen($otherVenue) >= strlen($venueName)) {
                    continue;
                }

                if (!self::nameStartsWithVenue($venueName, $otherVenue)) {
                    continue;
                }

                if ($hasContact) {
                    continue;
                }

                $warnings[] = [
                    'code' => 'venue_extends_known_venue',
                    'message' => sprintf(
                        'Venue %s looks like a variant of %s but has no phone or URL (often a duplicated event block)',
                        self::quote($venueName),
                        self::quote($otherVenue)
                    ),
                ];
                break;
            }
        }

        return self::dedupeWarnings($warnings);
    }

    /**
     * @param list<array{code: string, message: string}> $warnings
     * @return list<array{code: string, message: string}>
     */
    private static function dedupeWarnings(array $warnings): array
    {
        $seen = [];
        $out = [];

        foreach ($warnings as $warning) {
            $key = $warning['code'] . "\0" . $warning['message'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $warning;
        }

        return $out;
    }

    private static function eventLabel(array $event): string
    {
        $prefix = trim((string) ($event['prefix'] ?? ''));
        $title = trim((string) ($event['title'] ?? ''));

        if ($prefix !== '' && $title !== '') {
            return $prefix . ': ' . $title;
        }

        if ($title !== '') {
            return $title;
        }

        return trim((string) ($event['body'] ?? ''));
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * @param list<string> $venueNames
     */
    private static function shouldWarnDuplicateEvent(string $label, array $venueNames): bool
    {
        $normalizedLabel = self::normalize($label);
        $count = count($venueNames);

        if ($count < 2) {
            return false;
        }

        if ($count === 2) {
            [$a, $b] = $venueNames;

            if (self::namesLookRelated($a, $b)) {
                return true;
            }

            if (self::normalize($a) === $normalizedLabel || self::normalize($b) === $normalizedLabel) {
                return true;
            }
        }

        if (strlen($normalizedLabel) >= 40) {
            return true;
        }

        foreach ($venueNames as $venueName) {
            if (self::normalize($venueName) === $normalizedLabel) {
                return true;
            }
        }

        return false;
    }

    private static function namesLookRelated(string $a, string $b): bool
    {
        if ($a === $b) {
            return false;
        }

        return self::nameStartsWithVenue($a, $b) || self::nameStartsWithVenue($b, $a);
    }

    private static function nameStartsWithVenue(string $name, string $prefix): bool
    {
        if ($name === $prefix) {
            return false;
        }

        $pattern = '/^' . preg_quote($prefix, '/') . '(?:\s|$)/i';

        return preg_match($pattern, $name) === 1;
    }

    /**
     * @param list<string> $items
     */
    private static function quotedList(array $items): string
    {
        return implode(', ', array_map([self::class, 'quote'], $items));
    }

    private static function quote(string $value): string
    {
        return '"' . $value . '"';
    }
}
