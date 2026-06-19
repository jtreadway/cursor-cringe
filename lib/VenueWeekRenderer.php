<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/EventDateRenderer.php';
require_once __DIR__ . '/VenueFavorite.php';
require_once __DIR__ . '/VenueUtils.php';

class VenueWeekRenderer
{
    public const SCHEDULE_LINK_LABEL = '4-week schedule';

    /**
     * @param array<string, mixed> $venue
     */
    public static function venueNameMarkup(array $venue, string $scheduleHref = '', bool $linkName = true): string
    {
        $name = (string) ($venue['name'] ?? '');
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        if ($linkName && $scheduleHref !== '') {
            $markup = '<b><a href="' . htmlspecialchars($scheduleHref, ENT_QUOTES, 'UTF-8') . '">' . $escapedName . '</a></b>';
        } else {
            $markup = '<b>' . $escapedName . '</b>';
        }

        if (!empty($venue['url'])) {
            $url = (string) $venue['url'];
            $markup .= ' <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="venue-website-link">website</a>';
        }

        return $markup;
    }

    public static function scheduleLinkMarkup(string $href, string $venueName = ''): string
    {
        $label = $venueName !== ''
            ? self::SCHEDULE_LINK_LABEL . ' for ' . $venueName
            : self::SCHEDULE_LINK_LABEL;

        return ' <a class="venue-schedule-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
            . ' title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<svg class="venue-schedule-link__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14v10zM5 8V6h14v2H5zm2 4h2v2H7v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-8 4h2v2H7v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2z"/>'
            . '</svg>'
            . '<span class="venue-schedule-link__label">' . htmlspecialchars(self::SCHEDULE_LINK_LABEL, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</a>';
    }

    /**
     * @param array<string, mixed> $venue
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     */
    public static function render(array $venue, array $schedule, string $weekStart = ''): string
    {
        $name = (string) ($venue['name'] ?? '');
        $slug = (string) ($venue['slug'] ?? VenueUtils::slug((string) ($venue['name'] ?? '')));
        $tag = $weekStart !== '' ? 'div' : 'p';
        $linkName = $weekStart !== '';
        $venueWeekHref = $linkName
            ? 'venue.php?venue=' . rawurlencode($slug) . '&date=' . rawurlencode($weekStart)
            : '';

        $output = '<' . $tag . ' class="venue-block venue-week__block" data-venue-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
        $output .= VenueFavorite::buttonMarkup();
        $output .= self::venueNameMarkup($venue, $venueWeekHref, $linkName);

        $hasPhone = !empty($venue['phone']);
        $hasNote = !empty($venue['note']);

        if ($hasPhone && $hasNote) {
            $phone = (string) $venue['phone'];
            $note = (string) $venue['note'];
            $output .= ' - ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '<br>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        } elseif ($hasPhone) {
            $phone = (string) $venue['phone'];
            $output .= ' - ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
        } elseif ($hasNote) {
            $note = (string) $venue['note'];
            $output .= '<br>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        }

        if ($linkName) {
            $output .= self::scheduleLinkMarkup($venueWeekHref, $name);
        }

        $output .= "<br>\n";

        if ($schedule === []) {
            $emptyMessage = $weekStart !== ''
                ? 'No events listed for this venue this week.'
                : 'No events listed for this venue in the next 4 weeks.';
            $output .= '<span class="meta">' . $emptyMessage . '</span>';
            $output .= '</' . $tag . ">\n";

            return $output;
        }

        $lines = [];
        foreach ($schedule as $entry) {
            $dateShort = (string) $entry['day']['dateShort'];
            foreach ($entry['events'] as $event) {
                $tags = EventClassifier::tagsForEvent($event);
                $tagAttr = $tags !== [] ? ' data-tags="' . htmlspecialchars(implode(',', $tags), ENT_QUOTES, 'UTF-8') . '"' : '';
                $lines[] = '<span class="event-line"' . $tagAttr . '>' . EventDateRenderer::renderShort($dateShort) . ' '
                    . self::renderEvent($event) . '</span>';
            }
        }

        $output .= implode("\n", $lines);

        $output .= '</' . $tag . ">\n";

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

        return implode(' ', array_filter([$output, $parens, $time, $postfix], static fn ($part) => $part !== ''));
    }
}
