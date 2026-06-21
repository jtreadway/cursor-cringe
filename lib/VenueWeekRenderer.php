<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/EventDateRenderer.php';
require_once __DIR__ . '/VenueFavorite.php';
require_once __DIR__ . '/VenueUtils.php';

class VenueWeekRenderer
{
    public const VENUE_DETAILS_LABEL = 'Venue details';

    /**
     * @param array<string, mixed> $venue
     */
    public static function venueNameMarkupListing(array $venue): string
    {
        $name = (string) ($venue['name'] ?? '');
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        if (!empty($venue['url'])) {
            $url = (string) $venue['url'];

            return '<b><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                . $escapedName . '</a></b>';
        }

        return '<b>' . $escapedName . '</b>';
    }

    /**
     * @param array<string, mixed> $venue
     */
    public static function venueNameMarkupProfile(array $venue): string
    {
        $name = (string) ($venue['name'] ?? '');

        return '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
    }

    public static function phoneMarkup(string $phone): string
    {
        $tel = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if ($tel === '') {
            return htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
        }

        return '<a href="tel:' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '" class="venue-phone-link">'
            . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    public static function urlInlineMarkup(string $url): string
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="venue-url-link">'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /**
     * @param array<string, mixed> $venue
     */
    public static function venueProfileExtrasMarkup(
        array $venue,
        string $mapArea = 'Columbus, Ohio',
        string $mapAddress = ''
    ): string {
        $mapPreview = self::venueMapPreviewMarkup($venue, $mapArea, $mapAddress);
        if ($mapPreview === '') {
            return '';
        }

        return '<div class="venue-profile-extras">' . $mapPreview . '</div>';
    }

    /**
     * @param list<array{phone: string, url: string}> $contacts
     */
    public static function venueProfileContactsMarkup(array $contacts): string
    {
        if ($contacts === []) {
            return '';
        }

        $lines = [];
        foreach ($contacts as $contact) {
            $phone = trim($contact['phone']);
            $url = trim($contact['url']);
            $parts = [];

            if ($phone !== '') {
                $parts[] = self::phoneMarkup($phone);
            }

            if ($url !== '') {
                $parts[] = self::urlInlineMarkup($url);
            }

            if ($parts === []) {
                continue;
            }

            $lines[] = '<div class="venue-profile-contact">' . implode(' / ', $parts) . '</div>';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $addresses
     */
    public static function venueProfileAddressesMarkup(array $addresses): string
    {
        if ($addresses === []) {
            return '';
        }

        $lines = [];
        foreach ($addresses as $address) {
            $lines[] = '<div class="venue-profile-address">'
                . htmlspecialchars($address, ENT_QUOTES, 'UTF-8')
                . '</div>';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $venue
     */
    public static function venueMapPreviewMarkup(
        array $venue,
        string $mapArea = 'Columbus, Ohio',
        string $mapAddress = ''
    ): string {
        $name = (string) ($venue['name'] ?? '');
        if ($name === '') {
            return '';
        }

        $mapQuery = trim($mapAddress) !== ''
            ? trim($mapAddress) . ', ' . $mapArea
            : $name . ', ' . $mapArea;
        $mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($mapQuery) . '&z=17&output=embed';
        $title = 'Map of ' . $name;

        return '<div class="venue-map-preview">'
            . '<iframe class="venue-map-preview__embed" src="' . htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' loading="lazy" referrerpolicy="no-referrer-when-downgrade"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></iframe>'
            . '</div>';
    }

    public static function venueDetailsLinkMarkup(string $href, string $venueName = ''): string
    {
        $label = $venueName !== ''
            ? self::VENUE_DETAILS_LABEL . ' for ' . $venueName
            : self::VENUE_DETAILS_LABEL;

        return ' <a class="venue-details-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
            . ' title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<svg class="venue-details-link__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 10v5"/>'
            . '<circle cx="12" cy="7" r="0.5" fill="currentColor" stroke="none"/>'
            . '</svg>'
            . '</a>';
    }

    /**
     * Profile page: venue header + map (opens .venue-week__block; caller inserts filter before schedule).
     *
     * @param array<string, mixed> $venue
     * @param list<array<string, mixed>> $venueRecords
     */
    public static function renderProfileIntro(array $venue, array $venueRecords = []): string
    {
        $slug = (string) ($venue['slug'] ?? VenueUtils::slug((string) ($venue['name'] ?? '')));
        $contactInfo = VenueUtils::profileContactInfo($venueRecords !== [] ? $venueRecords : [$venue]);

        $output = '<div class="venue-block venue-week__block" data-venue-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
        $output .= VenueFavorite::buttonMarkup();
        $output .= self::venueNameMarkupProfile($venue);

        $contactsMarkup = self::venueProfileContactsMarkup($contactInfo['contacts']);
        if ($contactsMarkup !== '') {
            $output .= "\n" . $contactsMarkup;
        }

        $addressesMarkup = self::venueProfileAddressesMarkup($contactInfo['addresses']);
        if ($addressesMarkup !== '') {
            $output .= "\n" . $addressesMarkup;
        }

        $mapAddress = $contactInfo['addresses'][0] ?? '';
        $profileExtras = self::venueProfileExtrasMarkup($venue, 'Columbus, Ohio', $mapAddress);
        if ($profileExtras !== '') {
            $output .= "\n" . $profileExtras;
        }

        return $output;
    }

    /**
     * Event lines (or empty message) and closing tag for .venue-week__block.
     *
     * @param array<string, mixed> $venue
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     */
    public static function renderSchedule(array $venue, array $schedule, string $weekStart = ''): string
    {
        $output = "<br>\n";

        if ($schedule === []) {
            $emptyMessage = $weekStart !== ''
                ? 'No events listed for this venue this week.'
                : 'No events listed for this venue in the next 4 weeks.';
            $output .= '<span class="meta">' . $emptyMessage . '</span>';
            $output .= "</div>\n";

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
        $output .= "</div>\n";

        return $output;
    }

    /**
     * @param array<string, mixed> $venue
     * @param list<array{day: array<string, mixed>, venue: array<string, mixed>, events: list<array<string, mixed>>}> $schedule
     */
    public static function render(array $venue, array $schedule, string $weekStart = ''): string
    {
        $name = (string) ($venue['name'] ?? '');
        $slug = (string) ($venue['slug'] ?? VenueUtils::slug((string) ($venue['name'] ?? '')));
        $linkName = $weekStart !== '';
        $venueWeekHref = $linkName
            ? 'venue.php?venue=' . rawurlencode($slug) . '&date=' . rawurlencode($weekStart)
            : '';

        $output = '<div class="venue-block venue-week__block" data-venue-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
        $output .= VenueFavorite::buttonMarkup();
        $output .= $linkName
            ? self::venueNameMarkupListing($venue)
            : self::venueNameMarkupProfile($venue);

        if ($linkName) {
            $hasPhone = !empty($venue['phone']);
            $hasNote = !empty($venue['note']);

            if ($hasPhone && $hasNote) {
                $phone = (string) $venue['phone'];
                $note = (string) $venue['note'];
                $output .= ' - ' . self::phoneMarkup($phone) . '<br>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
            } elseif ($hasPhone) {
                $output .= ' - ' . self::phoneMarkup((string) $venue['phone']);
            } elseif ($hasNote) {
                $output .= '<br>' . htmlspecialchars((string) $venue['note'], ENT_QUOTES, 'UTF-8');
            }

            $output .= self::venueDetailsLinkMarkup($venueWeekHref, $name);

            return $output . self::renderSchedule($venue, $schedule, $weekStart);
        }

        $venueRecords = array_map(static fn (array $entry): array => $entry['venue'], $schedule);
        $venueRecords[] = $venue;

        return self::renderProfileIntro($venue, $venueRecords) . self::renderSchedule($venue, $schedule, $weekStart);
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
