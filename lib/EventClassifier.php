<?php

declare(strict_types=1);

class EventClassifier
{
    /**
     * @return array<string, array{priority: int, schema: string, keywords: list<string>, tags: list<string>}>
     */
    private static function rules(): array
    {
        static $rules = null;

        if ($rules === null) {
            $rules = require __DIR__ . '/event_type_rules.php';
        }

        return $rules;
    }

    /**
     * @return array{schemaType: string, tags: list<string>}
     */
    public static function classify(string $title, array $notes = [], string $venueName = ''): array
    {
        $text = strtolower(trim($title . ' ' . implode(' ', $notes) . ' ' . $venueName));
        $matched = [];

        foreach (self::rules() as $ruleId => $rule) {
            if ($ruleId === 'festival') {
                if (self::matchesFestival($text)) {
                    $matched[] = [
                        'schema' => $rule['schema'],
                        'priority' => $rule['priority'],
                        'tags' => $rule['tags'],
                    ];
                }
                continue;
            }

            foreach ($rule['keywords'] as $keyword) {
                if (self::keywordMatches($text, $keyword)) {
                    $matched[] = [
                        'schema' => $rule['schema'],
                        'priority' => $rule['priority'],
                        'tags' => $rule['tags'],
                    ];
                    break;
                }
            }
        }

        $tags = [];

        if ($matched !== []) {
            usort($matched, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

            foreach ($matched as $match) {
                $tags = array_merge($tags, $match['tags']);
            }

            $tags = array_values(array_unique($tags));
        }

        if (self::mentionsOpenMicOrJam($text)) {
            $tags = self::resolveOpenMicTags($text, $tags);
        }

        if ($tags === []) {
            return [
                'schemaType' => 'Event',
                'tags' => [],
            ];
        }

        return [
            'schemaType' => self::schemaForTags($tags),
            'tags' => $tags,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allFilterTags(): array
    {
        $tags = [];
        foreach (self::rules() as $rule) {
            $tags = array_merge($tags, $rule['tags']);
        }

        $tags[] = 'open mic';
        $tags[] = 'unknown';
        sort($tags);

        return array_values(array_unique($tags));
    }

    /**
     * @param list<string> $tags
     */
    public static function schemaForTags(array $tags): string
    {
        if ($tags === [] || $tags === ['unknown']) {
            return 'Event';
        }

        $best = null;

        foreach (self::rules() as $rule) {
            foreach ($rule['tags'] as $ruleTag) {
                if (!in_array($ruleTag, $tags, true)) {
                    continue;
                }

                if ($best === null || $rule['priority'] < $best['priority']) {
                    $best = $rule;
                }
            }
        }

        return $best['schema'] ?? 'Event';
    }

    /**
     * @param array<string, mixed> $day
     * @return array<string, int>
     */
    public static function tagCountsForDay(array $day): array
    {
        $counts = [];

        foreach ($day['venues'] ?? [] as $venue) {
            foreach ($venue['events'] ?? [] as $event) {
                foreach (self::tagsForEvent($event) as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $day
     * @return list<string>
     */
    public static function tagsForDay(array $day): array
    {
        return array_keys(self::tagCountsForDay($day));
    }

    /**
     * @param array<string, mixed> $week
     * @return array<string, int>
     */
    public static function tagCountsForWeek(array $week): array
    {
        $counts = [];

        foreach ($week['days'] ?? [] as $day) {
            foreach (self::tagCountsForDay($day) as $tag => $count) {
                $counts[$tag] = ($counts[$tag] ?? 0) + $count;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $week
     */
    public static function eventCountForWeek(array $week): int
    {
        $total = 0;

        foreach ($week['days'] ?? [] as $day) {
            foreach ($day['venues'] ?? [] as $venue) {
                $total += count($venue['events'] ?? []);
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $week
     */
    public static function venueCountForWeek(array $week): int
    {
        $slugs = [];

        foreach ($week['days'] ?? [] as $day) {
            foreach ($day['venues'] ?? [] as $venue) {
                if (count($venue['events'] ?? []) === 0) {
                    continue;
                }

                $slug = (string) ($venue['slug'] ?? '');
                if ($slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        return count($slugs);
    }

    /**
     * @param array<string, mixed> $day
     */
    public static function venueCountForDay(array $day): int
    {
        $count = 0;

        foreach ($day['venues'] ?? [] as $venue) {
            if (count($venue['events'] ?? []) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $week
     * @param list<string> $slugs
     */
    public static function venueCountForWeekSlugs(array $week, array $slugs): int
    {
        if ($slugs === []) {
            return 0;
        }

        $allowed = array_flip($slugs);
        $seen = [];

        foreach ($week['days'] ?? [] as $day) {
            foreach ($day['venues'] ?? [] as $venue) {
                if (count($venue['events'] ?? []) === 0) {
                    continue;
                }

                $slug = (string) ($venue['slug'] ?? '');
                if ($slug !== '' && isset($allowed[$slug])) {
                    $seen[$slug] = true;
                }
            }
        }

        return count($seen);
    }

    /**
     * @param array<string, mixed> $day
     * @param list<string> $slugs
     */
    public static function venueCountForDaySlugs(array $day, array $slugs): int
    {
        if ($slugs === []) {
            return 0;
        }

        $allowed = array_flip($slugs);
        $count = 0;

        foreach ($day['venues'] ?? [] as $venue) {
            if (count($venue['events'] ?? []) === 0) {
                continue;
            }

            $slug = (string) ($venue['slug'] ?? '');
            if ($slug !== '' && isset($allowed[$slug])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $week
     * @param list<string> $slugs
     * @return array<string, int>
     */
    public static function tagCountsForWeekSlugs(array $week, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $allowed = array_flip($slugs);
        $counts = [];

        foreach ($week['days'] ?? [] as $day) {
            foreach ($day['venues'] ?? [] as $venue) {
                $slug = (string) ($venue['slug'] ?? '');
                if ($slug === '' || !isset($allowed[$slug])) {
                    continue;
                }

                foreach ($venue['events'] ?? [] as $event) {
                    foreach (self::tagsForEvent($event) as $tag) {
                        $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                    }
                }
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $week
     * @param list<string> $slugs
     */
    public static function eventCountForWeekSlugs(array $week, array $slugs): int
    {
        if ($slugs === []) {
            return 0;
        }

        $allowed = array_flip($slugs);
        $total = 0;

        foreach ($week['days'] ?? [] as $day) {
            foreach ($day['venues'] ?? [] as $venue) {
                $slug = (string) ($venue['slug'] ?? '');
                if ($slug === '' || !isset($allowed[$slug])) {
                    continue;
                }

                $total += count($venue['events'] ?? []);
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $day
     * @param list<string> $slugs
     */
    public static function eventCountForDaySlugs(array $day, array $slugs): int
    {
        if ($slugs === []) {
            return 0;
        }

        $allowed = array_flip($slugs);
        $total = 0;

        foreach ($day['venues'] ?? [] as $venue) {
            $slug = (string) ($venue['slug'] ?? '');
            if ($slug === '' || !isset($allowed[$slug])) {
                continue;
            }

            $total += count($venue['events'] ?? []);
        }

        return $total;
    }

    /**
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     * @return array<string, int>
     */
    public static function tagCountsForSchedule(array $schedule): array
    {
        $counts = [];

        foreach ($schedule as $entry) {
            foreach ($entry['events'] as $event) {
                foreach (self::tagsForEvent($event) as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     * @return list<string>
     */
    public static function tagsForSchedule(array $schedule): array
    {
        return array_keys(self::tagCountsForSchedule($schedule));
    }

    /**
     * @param array<string, mixed> $event
     * @return list<string>
     */
    public static function tagsForEvent(array $event): array
    {
        if (!empty($event['tags']) && is_array($event['tags'])) {
            return array_values($event['tags']);
        }

        $notes = [];
        if (!empty($event['parens'])) {
            $notes[] = (string) $event['parens'];
        }
        if (!empty($event['prefix'])) {
            $notes[] = (string) $event['prefix'];
        }

        return self::classify(
            (string) ($event['display'] ?? $event['title'] ?? ''),
            $notes,
            (string) ($event['venueName'] ?? '')
        )['tags'];
    }

    private static function keywordMatches(string $text, string $keyword): bool
    {
        $keyword = strtolower($keyword);

        if ($keyword === 'jam') {
            return preg_match('/\bjam\b/', $text) === 1;
        }

        return strpos($text, $keyword) !== false;
    }

    private static function mentionsOpenMicOrJam(string $text): bool
    {
        return preg_match('/\bopen[\s-]?mic\b/i', $text) === 1
            || preg_match('/\bjam\b/', $text) === 1;
    }

    /**
     * @param list<string> $existingTags
     * @return list<string>
     */
    private static function resolveOpenMicTags(string $text, array $existingTags): array
    {
        if (self::isVarietyOpenMic($text)) {
            return array_values(array_unique(array_merge(
                ['open mic', 'variety'],
                self::detectOpenMicSubtypes($text)
            )));
        }

        $subtypes = self::detectOpenMicSubtypes($text);
        if ($subtypes === []) {
            $subtypes = array_values(array_filter(
                $existingTags,
                static fn (string $tag): bool => in_array($tag, ['comedy', 'spoken word', 'music'], true)
            ));
        }

        if ($subtypes !== []) {
            return array_values(array_unique(array_merge(['open mic'], $subtypes)));
        }

        $nonOpenMicTags = array_values(array_filter(
            $existingTags,
            static fn (string $tag): bool => $tag !== 'open mic'
        ));

        if (preg_match('/\bjam\b/', $text) && $nonOpenMicTags !== []) {
            return array_values(array_unique(array_merge(['open mic'], $nonOpenMicTags)));
        }

        return ['unknown'];
    }

    private static function isVarietyOpenMic(string $text): bool
    {
        if (preg_match('/\bvariety\b/i', $text) !== 1) {
            return false;
        }

        return preg_match('/\bopen[\s-]?mic\b/i', $text) === 1
            || preg_match('/\bopen\s+stage\b/i', $text) === 1
            || preg_match('/\bjam\b/', $text) === 1;
    }

    /**
     * @return list<string>
     */
    private static function detectOpenMicSubtypes(string $text): array
    {
        $tags = [];

        if (preg_match(
            '/\b(open[\s-]?mic\s+comedy|comedy\s+open[\s-]?mic|booked\s+comedy\s+open[\s-]?mic|rough\s+drafts)\b/i',
            $text
        )) {
            $tags[] = 'comedy';
        }

        if (preg_match(
            '/\b(poetry\s+open[\s-]?mic|open[\s-]?mic\b[^.]{0,40}\bpoetry\b|poetry\s+thing|village\s+poetry)\b/i',
            $text
        )) {
            $tags[] = 'spoken word';
        }

        if (preg_match(
            '/\b(music\s+open\s*mic|acoustic\s+open\s*mic|new\s+music\s+showcase)\b/i',
            $text
        )) {
            $tags[] = 'music';
        }

        return array_values(array_unique($tags));
    }

    private static function matchesFestival(string $text): bool
    {
        if (!preg_match_all('/\b(?:festival|\w*fest)\b/i', $text, $matches)) {
            return false;
        }

        foreach ($matches[0] as $match) {
            if (strcasecmp($match, 'manifest') !== 0) {
                return true;
            }
        }

        return false;
    }
}
