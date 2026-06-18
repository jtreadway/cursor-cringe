<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/EventDateRenderer.php';
require_once __DIR__ . '/VenueFavorite.php';

class VenueWeekRenderer
{
    /**
     * @param array<string, mixed> $venue
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     */
    public static function render(array $venue, array $schedule, string $weekStart = ''): string
    {
        $name = (string) ($venue['name'] ?? '');
        $slug = (string) ($venue['slug'] ?? '');
        $tag = $weekStart !== '' ? 'div' : 'p';

        if (!empty($venue['url'])) {
            $url = (string) $venue['url'];
            $output = '<' . $tag . ' class="venue-block venue-week__block" data-venue-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
            $output .= VenueFavorite::buttonMarkup();
            $output .= '<b><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a></b>';
        } else {
            $output = '<' . $tag . ' class="venue-block venue-week__block" data-venue-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
            $output .= VenueFavorite::buttonMarkup();
            $output .= '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
        }

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

        if ($weekStart !== '') {
            $venueWeekHref = 'venue.php?venue=' . rawurlencode($slug) . '&date=' . rawurlencode($weekStart);
            $output .= ' <a class="venue-week-link" href="' . htmlspecialchars($venueWeekHref, ENT_QUOTES, 'UTF-8') . '">all week</a>';
        }

        $output .= "<br>\n";

        if ($schedule === []) {
            $output .= '<span class="meta">No events listed for this venue this week.</span>';
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

        if ($weekStart !== '') {
            $output .= "\n<br>\n";
        }

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
