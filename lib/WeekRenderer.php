<?php

declare(strict_types=1);

/**
 * Renders week JSON into HTML matching calendar/streamParseAll4Weeks.php output.
 */
class WeekRenderer
{
    /**
     * @return array<string, mixed>
     */
    public static function loadWeek(string $jsonPath): array
    {
        if (!is_readable($jsonPath)) {
            throw new InvalidArgumentException("Cannot read week file: {$jsonPath}");
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in {$jsonPath}");
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $week
     */
    public static function renderWeek(array $week): string
    {
        $output = '';

        foreach ($week['days'] as $day) {
            $output .= self::renderDay($day);
        }

        return self::postProcess($output);
    }

    /**
     * Match calendarByDate::byDate() post-processing on week HTML.
     */
    public static function postProcess(string $output): string
    {
        $output = preg_replace('/  +/m', ' ', $output) ?? $output;
        $output = preg_replace('/ <\/p>/m', '</p>', $output) ?? $output;
        $output = preg_replace('/  +<br>/m', '<br>', $output) ?? $output;
        $output = preg_replace('/(\d)am -/mi', '$1am-', $output) ?? $output;
        $output = preg_replace('/(\d)pm -/mi', '$1pm-', $output) ?? $output;

        return $output;
    }

    /**
     * Render a single day for the interactive week carousel.
     *
     * @param array<string, mixed> $day
     */
    public static function renderDayPanel(array $day, string $weekStart): string
    {
        $anchor = (string) $day['anchor'];
        $date = (string) $day['date'];

        $output = '<article class="day-panel" id="day-' . $date . '" data-day-anchor="' . $anchor . '" data-date="' . $date . '">' . "\n";
        $output .= self::renderDayBodyForPanel($day, $weekStart);
        $output .= "</article>\n";

        return $output;
    }

    /**
     * @param array<string, mixed> $day
     */
    private static function renderDay(array $day): string
    {
        $anchor = (string) $day['anchor'];

        $output = '<li><a name="' . $anchor . '"></a>' . "\n";
        $output .= self::renderDayBody($day);
        $output .= "</li>\n\n";

        return $output;
    }

    /**
     * @param array<string, mixed> $day
     */
    private static function renderDayBodyForPanel(array $day, string $weekStart): string
    {
        $dateLabel = (string) $day['dateLabel'];
        $dateShort = (string) $day['dateShort'];

        $output = "\t<h3>" . $dateLabel . "</h3>\n\n";

        require_once __DIR__ . '/VenueScopeToggle.php';
        $output .= "\t" . VenueScopeToggle::markup();

        foreach ($day['venues'] as $venue) {
            $output .= self::renderVenueForPanel($venue, $dateShort, $weekStart);
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $day
     */
    private static function renderDayBody(array $day): string
    {
        $dateLabel = (string) $day['dateLabel'];
        $dateShort = (string) $day['dateShort'];

        $output = "\t<h3>" . $dateLabel . "</h3>\n\n";

        foreach ($day['venues'] as $venue) {
            $output .= self::renderVenue($venue, $dateShort);
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $venue
     */
    private static function renderVenueForPanel(array $venue, string $dateShort, string $weekStart): string
    {
        require_once __DIR__ . '/EventClassifier.php';
        require_once __DIR__ . '/VenueUtils.php';
        require_once __DIR__ . '/EventDateRenderer.php';
        require_once __DIR__ . '/VenueFavorite.php';

        $name = (string) $venue['name'];
        $slug = (string) ($venue['slug'] ?? VenueUtils::slug($name));
        $venueWeekHref = 'venue.php?venue=' . rawurlencode($slug) . '&date=' . rawurlencode($weekStart);

        if (!empty($venue['url'])) {
            $url = (string) $venue['url'];
            $output = "\t<p class=\"venue-block\" data-venue-slug=\"{$slug}\">" . VenueFavorite::buttonMarkup();
            $output .= '<b><a href="' . $url . '" target="_blank">' . $name . '</a></b>';
        } else {
            $output = "\t<p class=\"venue-block\" data-venue-slug=\"{$slug}\">" . VenueFavorite::buttonMarkup();
            $output .= '<b>' . $name . '</b>';
        }

        $hasPhone = !empty($venue['phone']);
        $hasNote = !empty($venue['note']);

        if ($hasPhone && $hasNote) {
            $phone = (string) $venue['phone'];
            $note = (string) $venue['note'];
            $output .= " - {$phone}<br>{$note}";
        } elseif ($hasPhone) {
            $phone = (string) $venue['phone'];
            $output .= " - {$phone}";
        } elseif ($hasNote) {
            $note = (string) $venue['note'];
            $output .= "<br>{$note}";
        }

        $output .= ' <a class="venue-week-link" href="' . $venueWeekHref . '">all week</a>';
        $output .= "<br>\n";

        $events = $venue['events'] ?? [];

        foreach ($events as $event) {
            $tags = EventClassifier::tagsForEvent($event);
            $tagAttr = $tags !== [] ? ' data-tags="' . htmlspecialchars(implode(',', $tags), ENT_QUOTES, 'UTF-8') . '"' : '';
            $output .= "\t<span class=\"event-line\"{$tagAttr}>" . EventDateRenderer::renderShort($dateShort) . ' ' . self::renderEvent($event) . "</span>\n";
        }

        $output .= "</p>\n\n";

        return $output;
    }

    /**
     * @param array<string, mixed> $venue
     */
    private static function renderVenue(array $venue, string $dateShort): string
    {
        $name = (string) $venue['name'];

        if (!empty($venue['url'])) {
            $url = (string) $venue['url'];
            $output = "\t<p><b><a href=\"{$url}\" target=\"_blank\">{$name}</a></b>";
        } else {
            $output = "\t<p><b>{$name}</b>";
        }

        $hasPhone = !empty($venue['phone']);
        $hasNote = !empty($venue['note']);

        if ($hasPhone && $hasNote) {
            $phone = (string) $venue['phone'];
            $note = (string) $venue['note'];
            $output .= " - {$phone}<br>{$note}";
        } elseif ($hasPhone) {
            $phone = (string) $venue['phone'];
            $output .= " - {$phone}";
        } elseif ($hasNote) {
            $note = (string) $venue['note'];
            $output .= "<br>{$note}";
        }

        $output .= "<br>\n";

        $events = $venue['events'] ?? [];
        $lastIndex = count($events) - 1;

        foreach ($events as $index => $event) {
            $output .= "\t<b>{$dateShort}:</b> " . self::renderEvent($event);
            if ($index === $lastIndex) {
                $output .= "</p>\n\n";
            } else {
                $output .= "<br>\n";
            }
        }

        if ($events === []) {
            $output .= "</p>\n\n";
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function renderEvent(array $event): string
    {
        $prefix = (string) ($event['prefix'] ?? '');
        $title = (string) ($event['title'] ?? '');
        $parens = (string) ($event['parens'] ?? '');
        $time = (string) ($event['time'] ?? '');
        $postfix = (string) ($event['postfix'] ?? '');

        if (!empty($event['url'])) {
            $url = (string) $event['url'];
            if ($prefix !== '') {
                $output = '<a href="' . $url . '" target="_blank">' . $prefix . '</a>:   ' . $title;
            } else {
                $output = '<a href="' . $url . '" target="_blank">' . $title . '</a>';
            }
        } elseif ($prefix !== '') {
            $output = $prefix . ': ' . $title;
        } else {
            $output = $title;
        }

        return implode(' ', [$output, $parens, $time, $postfix]);
    }
}
