<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/VenueUtils.php';
require_once __DIR__ . '/VenueWeekRenderer.php';
require_once __DIR__ . '/VenueScopeToggle.php';

class WeekByVenueRenderer
{
    /**
     * @param array<string, mixed> $week
     */
    public static function render(array $week, string $weekStart): string
    {
        $output = '<div class="week-by-venue" data-week-by-venue>' . "\n";
        $output .= VenueScopeToggle::markup();

        foreach (VenueUtils::venuesForWeek($week) as $entry) {
            if ($entry['schedule'] === []) {
                continue;
            }

            $output .= VenueWeekRenderer::render($entry['venue'], $entry['schedule'], $weekStart);
        }

        $output .= "</div>\n";

        return $output;
    }
}
