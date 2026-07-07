<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/calendar-common.php';
require_once __DIR__ . '/lib/WeekRenderer.php';
require_once __DIR__ . '/lib/CalendarSeo.php';
require_once __DIR__ . '/lib/EventClassifier.php';
require_once __DIR__ . '/lib/WeekByVenueRenderer.php';

$weeksDir = __DIR__ . '/weeks';
$available = availableWeeks($weeksDir);
$tz = showCalendarTimezone();
$today = calendarToday($tz);
$selectedDate = $today->format('Ymd');
$activeTags = parseTagsParam(isset($_GET['tags']) ? (string) $_GET['tags'] : null);
$findQuery = parseFindParam(isset($_GET['find']) ? (string) $_GET['find'] : null);
$viewMode = isset($_GET['view'])
    ? parseViewParam((string) $_GET['view'])
    : 'day';
$favoriteSlugs = parseSlugListCookie(isset($_COOKIE['cringe_favorites']) ? (string) $_COOKIE['cringe_favorites'] : null);
$scopeMode = resolveScopeMode(
    isset($_GET['scope']) ? (string) $_GET['scope'] : null,
    $favoriteSlugs
);

$redirectDate = $today->format('Ymd');
if (isset($_GET['date']) && preg_match('/^\d{8}$/', (string) $_GET['date'])) {
    $redirectDate = (string) $_GET['date'];
}
redirectToSavedPreferencesIfNeeded($redirectDate);

if (isset($_GET['date']) && preg_match('/^\d{8}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
    $thisWeek = mondayForDate($_GET['date']);
} elseif ($available !== []) {
    $thisWeek = defaultWeekMonday($weeksDir, $available);
} else {
    $thisWeek = mondayForDate($today->format('Ymd'));
}

$weekPath = $weeksDir . '/' . $thisWeek . '.json';
$weekMondayDt = DateTimeImmutable::createFromFormat('Ymd', $thisWeek, $tz) ?: $today;
$boundaryPrevDay = $weekMondayDt->modify('-1 day')->format('Ymd');
$boundaryNextDay = $weekMondayDt->modify('+6 days')->modify('+1 day')->format('Ymd');
$selectedDayDt = DateTimeImmutable::createFromFormat('Ymd', $selectedDate, $tz) ?: $today;
$prevWeekDate = $selectedDayDt->modify('-7 days')->format('Ymd');
$nextWeekDate = $selectedDayDt->modify('+7 days')->format('Ymd');
$weekHeader = weekHeader($thisWeek);
$hasWeek = is_readable($weekPath);
$dayPanels = '';
$weekByVenueHtml = '';
$startDayIndex = 0;
$selectedDay = null;
$pageTitle = 'Cringe Calendar POC';
$pageDescription = 'Columbus, Ohio live music calendar.';
$canonicalUrl = CalendarSeo::pageUrl($selectedDate);
$jsonLd = null;
$availableTags = [];
$tagCounts = [];
$totalEventCount = 0;
$favoriteEventCount = 0;
$totalVenueCount = 0;
$favoriteVenueCount = 0;

if ($hasWeek) {
    $weekData = WeekRenderer::loadWeek($weekPath);
    $startDayIndex = dayIndexForDate($weekData, $selectedDate);
    $selectedDay = $weekData['days'][$startDayIndex] ?? $weekData['days'][0];
    $selectedDate = (string) ($selectedDay['date'] ?? $selectedDate);
    $pageTitle = CalendarSeo::pageTitle($selectedDay);
    $pageDescription = CalendarSeo::metaDescription($selectedDay);
    $canonicalUrl = CalendarSeo::pageUrl($selectedDate);
    $jsonLd = CalendarSeo::jsonLd($selectedDay, $canonicalUrl);

    if ($viewMode === 'week') {
        $pageTitle = 'Columbus Live Music — ' . $weekHeader;
        $pageDescription = 'Live music and events in Columbus, Ohio for the week of ' . $weekHeader . '.';
        $tagCounts = EventClassifier::tagCountsForWeek($weekData);
        $totalEventCount = EventClassifier::eventCountForWeek($weekData);
        $totalVenueCount = EventClassifier::venueCountForWeek($weekData);
        $availableTags = array_keys($tagCounts);
        $weekByVenueHtml = WeekByVenueRenderer::render($weekData, $thisWeek);
    } else {
        $tagCounts = EventClassifier::tagCountsForDay($selectedDay);
        $availableTags = array_keys($tagCounts);
        foreach ($selectedDay['venues'] ?? [] as $venue) {
            $totalEventCount += count($venue['events'] ?? []);
        }
        $totalVenueCount = EventClassifier::venueCountForDay($selectedDay);
        foreach ($weekData['days'] as $day) {
            $dayPanels .= WeekRenderer::postProcess(WeekRenderer::renderDayPanel($day, $thisWeek));
        }
    }

    $favoriteEventCount = $viewMode === 'week'
        ? EventClassifier::eventCountForWeekSlugs($weekData, $favoriteSlugs)
        : EventClassifier::eventCountForDaySlugs($selectedDay, $favoriteSlugs);
    $favoriteVenueCount = $viewMode === 'week'
        ? EventClassifier::venueCountForWeekSlugs($weekData, $favoriteSlugs)
        : EventClassifier::venueCountForDaySlugs($selectedDay, $favoriteSlugs);
} else {
    $pageTitle = $viewMode === 'week'
        ? 'Columbus Live Music — ' . $weekHeader
        : 'Columbus Live Music — ' . weekHeader($thisWeek);
    $pageDescription = 'No calendar data for the week of ' . $weekHeader . '.';
    $canonicalUrl = CalendarSeo::pageUrl($selectedDate);
}

$dayLabels = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
$tagsQuery = $activeTags !== [] ? '&tags=' . rawurlencode(implode(',', $activeTags)) : '';
$findQueryParam = $findQuery !== '' ? '&find=' . rawurlencode($findQuery) : '';
$scopeQuery = scopeQueryForMode($scopeMode);
$prefsQuery = prefsQueryForRequest();
$filterQuery = filterQueryForRequest();
$viewQuery = viewQueryForMode($viewMode);
$bodyClasses = bodyClassForViewMode($viewMode);
$scopeActive = $scopeMode === 'favorites';
$filtersActive = $activeTags !== [] || $findQuery !== '' || $scopeActive;
$filterPanelOpen = $activeTags !== [] || $findQuery !== '' || filterPanelOpenInRequest();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($jsonLd !== null): ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <script>if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }</script>
    <link rel="stylesheet" href="assets/week-view.css?v=<?= (int) filemtime(__DIR__ . '/assets/week-view.css') ?>">
</head>
<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8') ?>" data-selected-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
<div class="wrap">
    <header>
        <h1>Live Shows Calendar</h1>
    </header>

    <?php
    $navPartial = __DIR__ . '/partials/calendar-nav-index.php';
    $showFilterActions = true;
    include __DIR__ . '/partials/calendar-filter-card.php';
    ?>

    <p class="pick">Proof of concept: week content is loaded from <code>weeks/<?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>.json</code>. Generate fresh JSON with <code>php generate.php</code> or <a href="generate.php">generate.php</a>. Regression-test with <code>php verify.php</code> or <a href="verify.php">verify.php</a>.</p>

    <?php if ($hasWeek): ?>
        <?php if ($viewMode === 'day'): ?>
        <p class="swipe-hint">Swipe or drag left/right to change day</p>
        <div
            class="week-carousel"
            data-week-carousel
            data-view-mode="day"
            data-start-index="<?= (int) $startDayIndex ?>"
            data-boundary-prev-day="<?= htmlspecialchars($boundaryPrevDay, ENT_QUOTES, 'UTF-8') ?>"
            data-boundary-next-day="<?= htmlspecialchars($boundaryNextDay, ENT_QUOTES, 'UTF-8') ?>"
            tabindex="0"
            aria-roledescription="carousel"
            aria-label="Days this week"
        >
            <div class="week-carousel__viewport">
                <div class="week-carousel__track">
                    <?= $dayPanels ?>
                </div>
            </div>
        </div>
        <?php elseif ($viewMode === 'week'): ?>
        <?= $weekByVenueHtml ?>
        <?php endif; ?>
    <?php else: ?>
        <p class="error">No calendar data for the week of <?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?> (<code>weeks/<?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>.json</code>). Use prev/next week to browse other dates, or run <code>php generate.php</code> to add data.</p>
    <?php endif; ?>
</div>

<script src="assets/calendar-prefs.js" defer></script>
<script src="assets/calendar-nav-sync.js" defer></script>
<?php if ($hasWeek): ?>
<script src="assets/venue-favorites.js" defer></script>
<script src="assets/venue-recent.js" defer></script>
<script src="assets/preferences-ui.js" defer></script>
<script src="assets/week-carousel.js" defer></script>
<script src="assets/event-filter.js" defer></script>
<?php endif; ?>
</body>
</html>
