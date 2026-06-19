<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/TagResolver.php';
require_once __DIR__ . '/TagSyntax.php';
require_once __DIR__ . '/VenueUtils.php';

/**
 * Parses cringe.com-style 4-week event listings from a -noTag text file.
 * Logic adapted from calendar/streamParseAll4Weeks.php.
 */
class Calendar
{
    private string $sourceFileDate;
    private array $DowdArray = [];
    private array $dArray = [];
    private array $yyyymmdd = [];
    private array $Monthdyyyy = [];
    private array $Monthd = [];
    private array $listings = [];

    public function __construct(string $sourcePath)
    {
        if (!is_readable($sourcePath)) {
            throw new InvalidArgumentException("Cannot read source file: {$sourcePath}");
        }

        if (!preg_match('/(\d{8})/', basename($sourcePath), $matches)) {
            throw new InvalidArgumentException('Source filename must contain YYYYMMDD');
        }

        $this->sourceFileDate = $matches[1];
        $fulldate = new DateTime($matches[1]);

        for ($i = 0; $i < 28; $i++) {
            $Dowd = $fulldate->format('D j');
            $this->DowdArray[] = $Dowd;
            $this->dArray[] = $fulldate->format('j');
            $this->yyyymmdd[$Dowd] = $fulldate->format('Ymd');
            $this->Monthdyyyy[$Dowd] = $fulldate->format('F j, Y');
            $this->Monthd[$Dowd] = $fulldate->format('F j');
            $fulldate->add(new DateInterval('P1D'));
        }

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read source file: {$sourcePath}");
        }

        $contents = $this->normalizeContents($contents);
        $this->listings = preg_split("/\n[\W]*\n+/m", $contents) ?: [];
    }

    public function getSourceFileDate(): string
    {
        return $this->sourceFileDate;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWeeks(): array
    {
        $dateArray = $this->getDateArray();
        $weeks = [];
        $weekDays = [];
        $weekStart = null;

        foreach ($this->DowdArray as $Dowd) {
            $dow = substr($Dowd, 0, 3);

            if ($dow === 'Mon') {
                $weekStart = $this->yyyymmdd[$Dowd];
                $weekDays = [];
            }

            $day = [
                'anchor' => $dow,
                'date' => $this->yyyymmdd[$Dowd],
                'dateLabel' => strtoupper($this->Monthdyyyy[$Dowd]),
                'dateShort' => $Dowd,
                'venues' => [],
            ];

            if (isset($dateArray[$Dowd])) {
                foreach ($dateArray[$Dowd] as $venue) {
                    $day['venues'][] = $this->formatVenueForJson($venue, $Dowd);
                }
            }

            $weekDays[] = $day;

            if ($dow === 'Sun' && $weekStart !== null) {
                $weeks[] = [
                    'weekStart' => $weekStart,
                    'days' => $weekDays,
                ];
            }
        }

        return $weeks;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array{venue: array<string, string>, events?: list<array<string, string>>}>
     */
    public function getParsedListings(): array
    {
        $listings = [];

        foreach ($this->listings as $listing) {
            $listings[] = $this->parseListing($listing);
        }

        return $listings;
    }

    /** @var list<string> */
    private array $errors = [];

    private function normalizeContents(string $contents): string
    {
        $contents = trim($contents);
        $contents = str_replace(['–', '’'], ["-", "'"], $contents);
        $contents = str_replace(' : ', ': ', $contents);
        $contents = preg_replace('/(  +)/m', ' ', $contents) ?? $contents;
        $contents = preg_replace('/(\$\d+)\.00/m', '\1', $contents) ?? $contents;
        $contents = preg_replace('/\(\$0/m', '(free', $contents) ?? $contents;
        $contents = preg_replace('/(\d):00 ?(am|pm|-)/mi', '\1\2', $contents) ?? $contents;
        $contents = preg_replace('/(\d)(\:00)? ?a\.?m\.?( ?(-)? ?)?( .*)/mi', '\1am\4\5', $contents) ?? $contents;
        $contents = preg_replace('/(\d)(\:00)? ?p\.?m\.?( ?(-)? ?)?( .*)/mi', '\1pm\4\5', $contents) ?? $contents;
        $contents = preg_replace('/12 ?am/mi', '12m', $contents) ?? $contents;
        $contents = preg_replace('/12 ?pm/mi', '12n', $contents) ?? $contents;
        $contents = preg_replace('/am ?- ?(.*)am/mi', '-\1am', $contents) ?? $contents;
        $contents = preg_replace('/pm ?- ?(.*)pm/mi', '-\1pm', $contents) ?? $contents;
        $contents = preg_replace('/(^| )0(\d: )/m', '\1\2', $contents) ?? $contents;
        $contents = preg_replace('/\(FREE/m', '(free', $contents) ?? $contents;
        $contents = preg_replace('/\(Free/m', '(free', $contents) ?? $contents;
        $contents = preg_replace('/\[\]/m', '', $contents) ?? $contents;
        $contents = preg_replace('/(\w)\[/m', '\1 [', $contents) ?? $contents;
        $contents = preg_replace('/  +/m', ' ', $contents) ?? $contents;
        $contents = preg_replace('/, (, )+/m', ' ', $contents) ?? $contents;
        $contents = preg_replace('/All Ages/m', 'all ages', $contents) ?? $contents;
        $contents = preg_replace('/Tiered Tickets/m', 'tiered tickets', $contents) ?? $contents;
        $contents = preg_replace('/\[Sold Out\]/m', '[SOLD OUT]', $contents) ?? $contents;

        return $contents;
    }

    /**
     * @return array{venue: array<string, string>, events?: list<array<string, string>>}
     */
    private function parseListing(string $listing): array
    {
        $allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $lines = preg_split("/\n/", $listing) ?: [];
        $venueLine = array_shift($lines);
        if ($venueLine === null) {
            return ['venue' => ['name' => '']];
        }

        $venueParts = preg_split("/ +\/ +| +- +/", $venueLine);
        [$venueName, $venueDefaults] = TagSyntax::stripVenueDefaults(array_shift($venueParts) ?? '');
        $venue = ['name' => $venueName];

        if ($venueDefaults !== []) {
            $venue['typesDefault'] = $venueDefaults;
        }

        foreach ($venueParts as $venuePart) {
            if (preg_match('/^(https?:\/\/.*)$/i', $venuePart, $matches)) {
                $venue['url'] = $matches[1];
            } elseif (preg_match('/^(\d\d\d(-|\.)\d\d\d(-|\.)\w\w\w\w)$/i', $venuePart, $matches)) {
                $venue['phone'] = $matches[1];
            } else {
                $venue['note'] = $venuePart;
            }
        }

        $output = ['venue' => $venue];
        $events = [];

        foreach ($lines as $line) {
            if (!preg_match("/^((\w\w\w|\d\d?\-\d\d?)(-\w\w\w)?( \d\d?)?): (.*)$/i", $line, $matches)) {
                continue;
            }

            $event = [
                'date' => $matches[1],
                'body' => $matches[5],
            ];

            $urlParts = explode(' http', $event['body'], 2);
            if (isset($urlParts[1])) {
                $event['body'] = $urlParts[0];
                $event['url'] = 'http' . $urlParts[1];
            }

            [$eventBody, $explicitTags] = TagSyntax::stripEventTags($event['body']);
            $event['body'] = $eventBody;
            if ($explicitTags !== null) {
                $event['explicitTags'] = $explicitTags;
            }

            preg_match(
                "/^(([^:]*): )?(.*?)(\([^)]*\))?( ([-\d :]*\d\d? ?(pm|am|m|n))(.*))?$/i",
                $event['body'],
                $bodyMatches
            );

            $event['prefix'] = trim($bodyMatches[2] ?? '');
            $event['title'] = trim($bodyMatches[3] ?? '');
            $event['parens'] = isset($bodyMatches[4]) ? trim($bodyMatches[4]) : '';
            $event['time'] = isset($bodyMatches[6]) ? trim($bodyMatches[6]) : '';
            $event['postfix'] = isset($bodyMatches[8]) ? trim($bodyMatches[8]) : '';

            if ($event['date'] === 'Mon-Sun') {
                foreach ($allDays as $day) {
                    $dayEvent = $event;
                    $dayEvent['date'] = $day;
                    $events[] = $dayEvent;
                }
            } else {
                $events[] = $event;
            }
        }

        if ($events !== []) {
            $output['events'] = $events;
        }

        return $output;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function getDateArray(): array
    {
        $output = [];
        $venueI = 0;

        foreach ($this->listings as $listing) {
            $parsed = $this->parseListing($listing);

            if (!isset($parsed['events'])) {
                $venueI++;
                continue;
            }

            foreach ($parsed['events'] as $event) {
                $this->assignEventToDates($output, $parsed['venue'], $event, $venueI);
            }

            $venueI++;
        }

        return $output;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $output
     * @param array<string, string> $venue
     * @param array<string, string> $event
     */
    private function assignEventToDates(array &$output, array $venue, array $event, int $venueI): void
    {
        $line = $this->formatEventKey($event);

        if (preg_match('/^(\d\d?)\-(\d\d?)\: (.*)$/i', $line, $matches)) {
            foreach ($this->DowdArray as $dowd) {
                $dom = (int) explode(' ', $dowd)[1];
                if ($dom >= (int) $matches[1] && $dom <= (int) $matches[2]) {
                    if (!isset($output[$dowd][$venueI])) {
                        $output[$dowd][$venueI] = $venue;
                    }
                    $output[$dowd][$venueI]['events'][] = $event;
                }
            }
            return;
        }

        if (!preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)( \d\d?)?\: (.*)$/i', $line, $matches)) {
            $this->errors[] = 'MALFORMED DATE: ' . $venue['name'] . ' - ' . $line;
            return;
        }

        if ($matches[1] && $matches[2] === '') {
            foreach ($this->DowdArray as $dowd) {
                if ($matches[1] === explode(' ', $dowd)[0]) {
                    if (!isset($output[$dowd][$venueI])) {
                        $output[$dowd][$venueI] = $venue;
                    }
                    $output[$dowd][$venueI]['events'][] = $event;
                }
            }
            return;
        }

        $Dowd = $matches[1] . $matches[2];
        $dowdArrayIndex = array_search($Dowd, $this->DowdArray, true);
        if ($dowdArrayIndex === false) {
            $dowdArrayIndex = array_search($Dowd, $this->dArray, true);
        }
        if ($dowdArrayIndex === false) {
            $this->errors[] = 'DATE MISMATCH: ' . $venue['name'] . ' - ' . $line;
            return;
        }

        $Dowd = $this->DowdArray[$dowdArrayIndex];
        if (!isset($output[$Dowd][$venueI])) {
            $output[$Dowd][$venueI] = $venue;
        }
        $output[$Dowd][$venueI]['events'][] = $event;
    }

    /**
     * @param array<string, string> $event
     */
    private function formatEventKey(array $event): string
    {
        $parts = array_filter([
            $event['prefix'] !== '' ? $event['prefix'] . ': ' . $event['title'] : $event['title'],
            $event['parens'],
            $event['time'],
            $event['postfix'],
        ], static fn ($part) => $part !== '');

        $body = trim(implode(' ', $parts));
        return $event['date'] . ': ' . $body;
    }

    /**
     * @param array<string, mixed> $venue
     * @return array<string, mixed>
     */
    private function formatVenueForJson(array $venue, string $dateShort): array
    {
        $formatted = [
            'name' => $venue['name'],
            'slug' => VenueUtils::slug($venue['name']),
        ];

        if (!empty($venue['url'])) {
            $formatted['url'] = $venue['url'];
        }
        if (!empty($venue['phone'])) {
            $formatted['phone'] = $venue['phone'];
        }
        if (!empty($venue['note'])) {
            $formatted['note'] = $venue['note'];
        }
        if (!empty($venue['typesDefault']) && is_array($venue['typesDefault'])) {
            $formatted['typesDefault'] = array_values($venue['typesDefault']);
        }

        $venueDefaults = $venue['typesDefault'] ?? [];
        $formatted['events'] = [];
        foreach ($venue['events'] as $event) {
            $formatted['events'][] = $this->formatEventForJson(
                $event,
                $dateShort,
                $venueDefaults,
                (string) $venue['name']
            );
        }

        return $formatted;
    }

    /**
     * @param array<string, string> $event
     * @param list<string> $venueDefaults
     * @return array<string, mixed>
     */
    private function formatEventForJson(
        array $event,
        string $dateShort,
        array $venueDefaults = [],
        string $venueName = ''
    ): array {
        $displayParts = array_filter([
            $event['prefix'] !== '' ? $event['prefix'] . ': ' . $event['title'] : $event['title'],
            $event['parens'],
            $event['time'],
            $event['postfix'],
        ], static fn ($part) => $part !== '');

        $formatted = [
            'dateShort' => $dateShort,
            'title' => $event['title'],
            'display' => trim(implode(' ', $displayParts)),
        ];

        if ($event['prefix'] !== '') {
            $formatted['prefix'] = $event['prefix'];
        }
        if ($event['parens'] !== '') {
            $formatted['parens'] = $event['parens'];
        }
        if ($event['time'] !== '') {
            $formatted['time'] = $event['time'];
        }
        if ($event['postfix'] !== '') {
            $formatted['postfix'] = $event['postfix'];
        }
        if (!empty($event['url'])) {
            $formatted['url'] = $event['url'];
        }

        $explicitTags = $event['explicitTags'] ?? null;
        $resolved = TagResolver::resolve(
            $venueDefaults,
            is_array($explicitTags) ? $explicitTags : null,
            $formatted['display'],
            [
                $event['prefix'] !== '' ? $event['prefix'] : null,
                $event['parens'] !== '' ? $event['parens'] : null,
            ],
            $venueName
        );

        if ($resolved['tags'] !== []) {
            $formatted['tags'] = $resolved['tags'];
        }
        $formatted['schemaType'] = $resolved['schemaType'];

        return $formatted;
    }
}
