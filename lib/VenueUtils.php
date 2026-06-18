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
}
