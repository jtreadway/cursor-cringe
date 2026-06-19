<?php

declare(strict_types=1);

require_once __DIR__ . '/VenueFavorite.php';

class VenueManageRenderer
{
    /**
     * @param list<array{name: string, slugs: list<string>}> $venues
     */
    public static function renderList(array $venues): string
    {
        if ($venues === []) {
            return '<p class="venues-manage__empty">No venues found in the available calendar weeks.</p>';
        }

        $output = '<div class="venues-manage" data-venues-manage>';

        foreach ($venues as $entry) {
            $name = (string) $entry['name'];
            $slugs = $entry['slugs'];
            $primarySlug = $slugs[0] ?? '';

            $output .= '<button type="button" class="venue-block venue-manage__row"';
            $output .= ' data-venue-favorite';
            $output .= ' data-venue-name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"';
            $output .= ' data-venue-slug="' . htmlspecialchars($primarySlug, ENT_QUOTES, 'UTF-8') . '"';
            $output .= ' data-venue-slugs="' . htmlspecialchars(implode(',', $slugs), ENT_QUOTES, 'UTF-8') . '"';
            $output .= ' aria-pressed="false"';
            $output .= ' aria-label="Add ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' to favorites">';
            $output .= VenueFavorite::iconMarkup();
            $output .= htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $output .= "</button>\n";
        }

        $output .= "</div>\n";

        return $output;
    }
}
