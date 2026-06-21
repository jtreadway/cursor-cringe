<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/calendar-common.php';
require_once __DIR__ . '/lib/WeekRenderer.php';
require_once __DIR__ . '/lib/VenueUtils.php';
require_once __DIR__ . '/lib/VenueWeekRenderer.php';
require_once __DIR__ . '/lib/EventClassifier.php';

$weeksDir = __DIR__ . '/weeks';
$venueSlug = isset($_GET['venue']) ? VenueUtils::slug((string) $_GET['venue']) : '';
$selectedDate = calendarToday(showCalendarTimezone())->format('Ymd');

if (isset($_GET['date']) && preg_match('/^\d{8}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
}

$thisWeek = mondayForDate($selectedDate);
$weekMondays = weekWindowForDate($selectedDate, $weeksDir);
$windowStart = $weekMondays[0] ?? $thisWeek;
$venueWeekSpan = venueWeekSpan();
$prevWindowStart = adjacentWeekWindowStart($windowStart, $weeksDir, -1);
$nextWindowStart = adjacentWeekWindowStart($windowStart, $weeksDir, 1);
$prevWeekDate = $prevWindowStart ?? $windowStart;
$nextWeekDate = $nextWindowStart ?? $windowStart;
$lastWeekMonday = $weekMondays !== [] ? $weekMondays[array_key_last($weekMondays)] : $thisWeek;
$weekHeader = weekRangeHeader($windowStart, $lastWeekMonday);
$pageTitle = 'Venue calendar';
$availableTags = [];
$tagCounts = [];
$totalEventCount = 0;
$favoriteEventCount = 0;
$totalVenueCount = 0;
$favoriteVenueCount = 0;
$venue = null;
$schedule = [];
$venueRecordsForProfile = [];
$hasWeek = $venueSlug !== '' && $weekMondays !== [];
$favoriteSlugs = parseSlugListCookie(isset($_COOKIE['cringe_favorites']) ? (string) $_COOKIE['cringe_favorites'] : null);
$activeTags = parseTagsParam(isset($_GET['tags']) ? (string) $_GET['tags'] : null);
$findQuery = parseFindParam(isset($_GET['find']) ? (string) $_GET['find'] : null);
$viewMode = isset($_GET['view'])
    ? parseViewParam((string) $_GET['view'])
    : parseViewParam(isset($_COOKIE['cringe_view']) ? (string) $_COOKIE['cringe_view'] : null);
$scopeMode = resolveScopeMode(
    isset($_GET['scope']) ? (string) $_GET['scope'] : null,
    $favoriteSlugs
);

if (!preferencesExplicitInRequest()) {
    $savedPrefs = loadSavedPreferencesFromCookies();
    if (hasSavedPreferencesFromCookies($savedPrefs)) {
        $activeTags = $savedPrefs['tags'];
        $findQuery = $savedPrefs['find'];
        $scopeMode = $savedPrefs['scope'];
    }
}

if ($hasWeek) {
    foreach ($weekMondays as $monday) {
        $weekData = WeekRenderer::loadWeek($weeksDir . '/' . $monday . '.json');
        $foundVenue = VenueUtils::findInWeek($weekData, $venueSlug);

        if ($foundVenue !== null) {
            $venue = $foundVenue;
            $venueRecordsForProfile[] = $foundVenue;
        }

        foreach (VenueUtils::weekScheduleForVenue($weekData, $venueSlug) as $entry) {
            $schedule[] = $entry;
            $venueRecordsForProfile[] = $entry['venue'];
        }
    }
}

if ($venue !== null) {
    $venueName = (string) ($venue['name'] ?? $venueSlug);
    $pageTitle = $venueName . ' — ' . $weekHeader;
    $tagCounts = EventClassifier::tagCountsForSchedule($schedule);
    $availableTags = array_keys($tagCounts);

    foreach ($schedule as $entry) {
        $totalEventCount += count($entry['events']);
    }

    $favoriteEventCount = in_array($venueSlug, $favoriteSlugs, true) ? $totalEventCount : 0;
    $totalVenueCount = $totalEventCount > 0 ? 1 : 0;
    $favoriteVenueCount = ($totalEventCount > 0 && in_array($venueSlug, $favoriteSlugs, true)) ? 1 : 0;
}

$tagsQuery = $activeTags !== [] ? '&tags=' . rawurlencode(implode(',', $activeTags)) : '';
$findQueryParam = $findQuery !== '' ? '&find=' . rawurlencode($findQuery) : '';
$scopeQuery = scopeQueryForMode($scopeMode);
$prefsQuery = prefsQueryForRequest();
$filterQuery = '&filter=open';
$viewQuery = viewQueryForMode($viewMode);
$navQuery = $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . $filterQuery;
$hideVenueScope = true;
$filterAlwaysOpen = true;
$filterNavSeparate = true;
$scopeActive = $scopeMode === 'favorites';
$filtersActive = $activeTags !== [] || $findQuery !== '';
$filterPanelOpen = true;
$backUrl = 'index.php?date=' . rawurlencode($selectedDate) . $viewQuery . $navQuery;
$venueQuery = 'venue=' . rawurlencode($venueSlug) . '&date=';
$prevVenueHref = 'venue.php?' . $venueQuery . rawurlencode($prevWeekDate) . $navQuery;
$nextVenueHref = 'venue.php?' . $venueQuery . rawurlencode($nextWeekDate) . $navQuery;
$backLabel = $viewMode === 'week' ? '← Back to week view' : '← Back to day view';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/week-view.css?v=<?= (int) filemtime(__DIR__ . '/assets/week-view.css') ?>">
</head>
<body class="venue-page"<?php if ($venueSlug !== ''): ?> data-track-venue="<?= htmlspecialchars($venueSlug, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
<div class="wrap">
    <header class="venue-page__header">
        <p class="meta"><a
            href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-nav-sync
            data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
            data-nav-view="<?= htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') ?></a></p>
    </header>

    <?php if ($hasWeek && $venue !== null): ?>
    <?= VenueWeekRenderer::renderProfileIntro($venue, $venueRecordsForProfile) ?>
    <?php
    $showFilterActions = true;
    include __DIR__ . '/partials/calendar-filter-card.php';
    include __DIR__ . '/partials/venue-week-nav.php';
    ?>
    <?= VenueWeekRenderer::renderSchedule($venue, $schedule) ?>
    <?php elseif ($venueSlug === ''): ?>
        <p class="error">Missing venue parameter.</p>
    <?php elseif (!$hasWeek): ?>
        <p class="error">No calendar data for week <?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>.</p>
    <?php else: ?>
        <p class="error">Venue not found in the next <?= (int) $venueWeekSpan ?> weeks.</p>
    <?php endif; ?>
</div>

<script src="assets/calendar-prefs.js" defer></script>
<script src="assets/calendar-nav-sync.js" defer></script>
<?php if ($venueSlug !== ''): ?>
<script src="assets/venue-recent.js" defer></script>
<?php endif; ?>
<?php if ($hasWeek && $venue !== null): ?>
<script src="assets/venue-favorites.js" defer></script>
<script src="assets/preferences-ui.js" defer></script>
<script src="assets/event-filter.js" defer></script>
<?php endif; ?>
</body>
</html>
