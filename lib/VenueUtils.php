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

        return $venues;
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
}
