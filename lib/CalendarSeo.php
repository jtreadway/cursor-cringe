<?php

declare(strict_types=1);

/**
 * SEO metadata and JSON-LD for the calendar day view.
 */
class CalendarSeo
{
    /**
     * @param array<string, mixed> $day
     */
    public static function pageTitle(array $day): string
    {
        $label = (string) ($day['dateLabel'] ?? '');

        return 'Columbus Live Music — ' . ucwords(strtolower($label));
    }

    /**
     * @param array<string, mixed> $day
     */
    public static function metaDescription(array $day): string
    {
        $eventCount = self::countEvents($day);
        $label = (string) ($day['dateLabel'] ?? '');
        $venueCount = count($day['venues'] ?? []);

        return sprintf(
            'Columbus, Ohio live music calendar for %s: %d listings across %d venues.',
            $label,
            $eventCount,
            $venueCount
        );
    }

    public static function pageUrl(string $dateYmd): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = strtok($_SERVER['SCRIPT_NAME'] ?? '/index.php', '?') ?: '/index.php';

        return $scheme . '://' . $host . $path . '?date=' . rawurlencode($dateYmd);
    }

    /**
     * @param array<string, mixed> $day
     * @return array<string, mixed>
     */
    public static function jsonLd(array $day, string $pageUrl): array
    {
        $isoDate = self::isoDate((string) ($day['date'] ?? ''));
        $listItems = [];
        $position = 1;

        foreach ($day['venues'] ?? [] as $venue) {
            foreach ($venue['events'] ?? [] as $event) {
                $listItems[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'item' => self::eventNode($day, $venue, $event, $isoDate, $pageUrl),
                ];
                $position++;
            }
        }

        $pageName = self::pageTitle($day);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $pageUrl . '#webpage',
                    'url' => $pageUrl,
                    'name' => $pageName,
                    'description' => self::metaDescription($day),
                    'inLanguage' => 'en-US',
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => 'Cringe Columbus Live Shows Calendar',
                    ],
                    'breadcrumb' => [
                        '@id' => $pageUrl . '#breadcrumb',
                    ],
                    'mainEntity' => [
                        '@id' => $pageUrl . '#eventlist',
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Live Shows Calendar',
                            'item' => self::calendarHomeUrl(),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => (string) ($day['dateLabel'] ?? ''),
                            'item' => $pageUrl,
                        ],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => $pageUrl . '#eventlist',
                    'name' => 'Live music events on ' . (string) ($day['dateLabel'] ?? ''),
                    'numberOfItems' => count($listItems),
                    'itemListElement' => $listItems,
                ],
            ],
        ];
    }

    private static function calendarHomeUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = strtok($_SERVER['SCRIPT_NAME'] ?? '/index.php', '?') ?: '/index.php';

        return $scheme . '://' . $host . $path;
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $venue
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private static function eventNode(
        array $day,
        array $venue,
        array $event,
        string $isoDate,
        string $pageUrl
    ): array {
        $name = (string) ($event['display'] ?? $event['title'] ?? 'Live music event');
        $node = [
            '@type' => 'Event',
            'name' => $name,
            'description' => $name,
            'startDate' => $isoDate,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => self::placeNode($venue),
        ];

        if (!empty($event['url'])) {
            $node['url'] = (string) $event['url'];
        } else {
            $node['url'] = $pageUrl;
        }

        if (!empty($day['dateLabel'])) {
            $node['organizer'] = [
                '@type' => 'Organization',
                'name' => 'Cringe Columbus Live Shows Calendar',
            ];
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $venue
     * @return array<string, mixed>
     */
    private static function placeNode(array $venue): array
    {
        $place = [
            '@type' => 'Place',
            'name' => (string) ($venue['name'] ?? 'Venue'),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Columbus',
                'addressRegion' => 'OH',
                'addressCountry' => 'US',
            ],
        ];

        if (!empty($venue['url'])) {
            $place['url'] = (string) $venue['url'];
        }

        if (!empty($venue['phone'])) {
            $place['telephone'] = (string) $venue['phone'];
        }

        return $place;
    }

    /**
     * @param array<string, mixed> $day
     */
    private static function countEvents(array $day): int
    {
        $count = 0;

        foreach ($day['venues'] ?? [] as $venue) {
            $count += count($venue['events'] ?? []);
        }

        return $count;
    }

    private static function isoDate(string $ymd): string
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', $ymd);

        return $date !== false ? $date->format('Y-m-d') : $ymd;
    }
}
