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

        if ($matched === []) {
            return [
                'schemaType' => 'Event',
                'tags' => [],
            ];
        }

        usort($matched, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $tags = [];
        foreach ($matched as $match) {
            $tags = array_merge($tags, $match['tags']);
        }

        return [
            'schemaType' => $matched[0]['schema'],
            'tags' => array_values(array_unique($tags)),
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
        return strpos($text, strtolower($keyword)) !== false;
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
