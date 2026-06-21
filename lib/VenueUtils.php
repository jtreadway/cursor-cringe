<?php

declare(strict_types=1);

class VenueUtils
{
    public static function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    public static function sortKeyForName(string $name): string
    {
        $name = trim($name);

        if (preg_match('/^(the|a|an)\s+/i', $name, $matches) === 1) {
            $name = substr($name, strlen($matches[0]));
        }

        return strtolower(trim($name));
    }

    public static function compareNames(string $a, string $b): int
    {
        return strcasecmp(self::sortKeyForName($a), self::sortKeyForName($b));
    }

    /**
     * @param array<string, mixed> $week
     * @return array<string, mixed>|null
     */
    public static function findInWeek(array $week, string $venueSlug): ?array
    {
        foreach ($week['days'] as $day) {
            foreach ($day['venues'] as $venue) {
                $slug = (string) ($venue['slug'] ?? self::slug((string) $venue['name']));
                if ($slug === $venueSlug) {
                    return $venue;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $week
     * @return list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}>
     */
    public static function weekScheduleForVenue(array $week, string $venueSlug): array
    {
        $schedule = [];

        foreach ($week['days'] as $day) {
            foreach ($day['venues'] as $venue) {
                $slug = (string) ($venue['slug'] ?? self::slug((string) $venue['name']));
                if ($slug !== $venueSlug) {
                    continue;
                }

                $events = $venue['events'] ?? [];
                if ($events === []) {
                    continue;
                }

                $schedule[] = [
                    'day' => $day,
                    'venue' => $venue,
                    'events' => $events,
                ];
            }
        }

        return $schedule;
    }

    /**
     * @param array<string, mixed> $week
     * @return list<array{venue: array<string, mixed>, schedule: list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}>}>
     */
    public static function venuesForWeek(array $week): array
    {
        $venues = [];
        $indexBySlug = [];

        foreach ($week['days'] as $day) {
            foreach ($day['venues'] as $venue) {
                $slug = (string) ($venue['slug'] ?? self::slug((string) $venue['name']));

                if (!isset($indexBySlug[$slug])) {
                    $indexBySlug[$slug] = count($venues);
                    $venues[] = [
                        'venue' => $venue,
                        'schedule' => [],
                    ];
                } else {
                    $venues[$indexBySlug[$slug]]['venue'] = self::mergeVenue(
                        $venues[$indexBySlug[$slug]]['venue'],
                        $venue
                    );
                }

                $events = $venue['events'] ?? [];
                if ($events === []) {
                    continue;
                }

                $venues[$indexBySlug[$slug]]['schedule'][] = [
                    'day' => $day,
                    'venue' => $venue,
                    'events' => $events,
                ];
            }
        }

        usort(
            $venues,
            static fn (array $a, array $b): int => self::compareNames(
                (string) ($a['venue']['name'] ?? ''),
                (string) ($b['venue']['name'] ?? '')
            )
        );

        return $venues;
    }

    /**
     * @param list<array<string, mixed>> $venueRecords
     * @return array{
     *     contacts: list<array{phone: string, url: string}>,
     *     addresses: list<string>
     * }
     */
    public static function profileContactInfo(array $venueRecords): array
    {
        $contacts = [];
        $addresses = [];

        foreach ($venueRecords as $venue) {
            $phone = trim((string) ($venue['phone'] ?? ''));
            $url = trim((string) ($venue['url'] ?? ''));
            $note = trim((string) ($venue['note'] ?? ''));

            if ($phone !== '' || $url !== '') {
                $key = self::normalizePhone($phone) . '|' . self::normalizeUrl($url);
                $contacts[$key] = [
                    'phone' => $phone,
                    'url' => $url,
                ];
            }

            if ($note !== '' && self::isStreetAddress($note)) {
                $addresses[$note] = $note;
            }
        }

        $contactList = array_values($contacts);
        usort(
            $contactList,
            static function (array $a, array $b): int {
                $phoneCompare = strcasecmp($a['phone'], $b['phone']);
                if ($phoneCompare !== 0) {
                    return $phoneCompare;
                }

                return strcasecmp($a['url'], $b['url']);
            }
        );

        $addressList = array_values($addresses);
        sort($addressList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'contacts' => $contactList,
            'addresses' => $addressList,
        ];
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone) ?? '';
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return rtrim(strtolower($url), '/');
    }

    public static function isStreetAddress(string $note): bool
    {
        $note = trim($note);
        if ($note === '') {
            return false;
        }

        if (strpos($note, '@') !== false) {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $note) === 1) {
            return false;
        }

        if (preg_match(
            '/\b(st|street|ave|avenue|blvd|boulevard|rd|road|dr|drive|ln|lane|ct|court|pl|place|way|pkwy|parkway|hwy|highway|suite|ste|unit)\b\.?/i',
            $note
        ) === 1) {
            return true;
        }

        return preg_match('/^\d+\s+\S/', $note) === 1;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private static function mergeVenue(array $existing, array $incoming): array
    {
        foreach (['name', 'url', 'phone', 'note', 'slug'] as $key) {
            if (empty($existing[$key]) && !empty($incoming[$key])) {
                $existing[$key] = $incoming[$key];
            }
        }

        return $existing;
    }

    /**
     * Unique venue names across multiple weeks, merged when the same name appears with different slugs.
     *
     * @param list<array<string, mixed>> $weeks
     * @return list<array{name: string, slugs: list<string>}>
     */
    public static function venuesByNameFromWeeks(array $weeks): array
    {
        $byName = [];

        foreach ($weeks as $week) {
            foreach ($week['days'] ?? [] as $day) {
                foreach ($day['venues'] ?? [] as $venue) {
                    $name = trim((string) ($venue['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $slug = (string) ($venue['slug'] ?? self::slug($name));
                    if ($slug === '') {
                        continue;
                    }

                    if (!isset($byName[$name])) {
                        $byName[$name] = [];
                    }

                    $byName[$name][$slug] = true;
                }
            }
        }

        $venues = [];

        foreach ($byName as $name => $slugSet) {
            $slugs = array_keys($slugSet);
            sort($slugs);
            $venues[] = [
                'name' => $name,
                'slugs' => $slugs,
            ];
        }

        usort(
            $venues,
            static fn (array $a, array $b): int => self::compareNames($a['name'], $b['name'])
        );

        return $venues;
    }
}
