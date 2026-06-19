<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/calendar-common.php';
require_once __DIR__ . '/lib/WeekRenderer.php';
require_once __DIR__ . '/lib/VenuesIndex.php';
require_once __DIR__ . '/lib/VenueManageRenderer.php';

$venuesIndexPath = __DIR__ . '/venues.json';
$weeksDir = __DIR__ . '/weeks';
$selectedDate = calendarToday(showCalendarTimezone())->format('Ymd');

if (isset($_GET['date']) && preg_match('/^\d{8}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
}

$viewMode = isset($_GET['view'])
    ? parseViewParam((string) $_GET['view'])
    : parseViewParam(isset($_COOKIE['cringe_view']) ? (string) $_COOKIE['cringe_view'] : null);

if (is_readable($venuesIndexPath)) {
    $venuesIndex = VenuesIndex::load($venuesIndexPath);
} else {
    $venuesIndex = VenuesIndex::buildFromWeekStarts(
        $weeksDir,
        static fn (string $path): array => WeekRenderer::loadWeek($path)
    );
    VenuesIndex::save($venuesIndexPath, $venuesIndex);
}

$venues = $venuesIndex['venues'];
$favoriteSlugs = parseSlugListCookie(isset($_COOKIE['cringe_favorites']) ? (string) $_COOKIE['cringe_favorites'] : null);
$venueListHtml = VenueManageRenderer::renderList($venues);
$venueCount = (int) ($venuesIndex['venueCount'] ?? count($venues));
$favoriteVenueCount = 0;

foreach ($venues as $entry) {
    foreach ($entry['slugs'] as $slug) {
        if (in_array($slug, $favoriteSlugs, true)) {
            $favoriteVenueCount++;
            break;
        }
    }
}

$tagsQuery = isset($_GET['tags']) && $_GET['tags'] !== '' ? '&tags=' . rawurlencode((string) $_GET['tags']) : '';
$findQueryParam = isset($_GET['find']) && $_GET['find'] !== '' ? '&find=' . rawurlencode((string) $_GET['find']) : '';
$scopeQuery = isset($_GET['scope']) && $_GET['scope'] === 'favorites' ? '&scope=favorites' : '';
$prefsQuery = prefsQueryForRequest();
$viewQuery = viewQueryForMode($viewMode);
$backUrl = 'index.php?date=' . rawurlencode($selectedDate) . $viewQuery . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery;
$backLabel = $viewMode === 'week' ? '← Back to week view' : '← Back to calendar';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage venues — Live Shows Calendar</title>
    <link rel="stylesheet" href="assets/week-view.css?v=<?= (int) filemtime(__DIR__ . '/assets/week-view.css') ?>">
</head>
<body class="venues-page" data-selected-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
<div class="wrap">
    <header class="venues-page__header">
        <h1>Manage venues</h1>
        <p class="meta">
            <?= (int) $venueCount ?> venues in the directory.
            Tap the heart to add or remove favorites, then save.
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') ?></a>
        </p>
    </header>

    <div class="venues-manage__toolbar">
        <p class="venues-manage__summary"><?= (int) $favoriteVenueCount ?> favorite<?= $favoriteVenueCount === 1 ? '' : 's' ?> selected</p>
        <div class="event-filter__actions venues-manage__actions" data-prefs-actions data-prefs-favorites-only>
            <button type="button" class="event-filter__action event-filter__action--save" data-prefs-save>save favorites</button>
        </div>
    </div>

    <?= $venueListHtml ?>
</div>

<script src="assets/calendar-prefs.js" defer></script>
<script src="assets/venue-favorites.js" defer></script>
<script src="assets/preferences-ui.js" defer></script>
<script src="assets/venues-manage.js" defer></script>
</body>
</html>
