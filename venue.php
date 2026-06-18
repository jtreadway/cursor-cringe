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
$weekPath = $weeksDir . '/' . $thisWeek . '.json';
$hasWeek = is_readable($weekPath) && $venueSlug !== '';
$venueName = '';
$schedule = [];
$activeTags = parseTagsParam(isset($_GET['tags']) ? (string) $_GET['tags'] : null);
$prevWeekDate = DateTimeImmutable::createFromFormat('Ymd', $selectedDate, showCalendarTimezone())
    ->modify('-7 days')
    ->format('Ymd');
$nextWeekDate = DateTimeImmutable::createFromFormat('Ymd', $selectedDate, showCalendarTimezone())
    ->modify('+7 days')
    ->format('Ymd');
$weekHeader = weekHeader($thisWeek);
$venueHtml = '';
$pageTitle = 'Venue calendar';
$availableTags = [];
$tagCounts = [];

if ($hasWeek) {
    $weekData = WeekRenderer::loadWeek($weekPath);
    $schedule = VenueUtils::weekScheduleForVenue($weekData, $venueSlug);
    $venue = VenueUtils::findInWeek($weekData, $venueSlug);
    $venueName = (string) ($venue['name'] ?? $venueSlug);
    $venueHtml = VenueWeekRenderer::render($venue, $schedule);
    $pageTitle = $venueName . ' — ' . $weekHeader;
    $tagCounts = EventClassifier::tagCountsForSchedule($schedule);
    $availableTags = array_keys($tagCounts);
    $activeTags = array_values(array_intersect($activeTags, $availableTags));
}

$backUrl = 'index.php?date=' . rawurlencode($selectedDate);
if ($activeTags !== []) {
    $backUrl .= '&tags=' . rawurlencode(implode(',', $activeTags));
}

$venueQuery = 'venue=' . rawurlencode($venueSlug) . '&date=';
$tagsQuery = $activeTags !== [] ? '&tags=' . rawurlencode(implode(',', $activeTags)) : '';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/week-view.css">
</head>
<body class="venue-page">
<div class="wrap">
    <header class="venue-page__header">
        <p class="meta"><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">← Back to day view</a></p>
    </header>

    <nav class="week-nav" aria-label="Week navigation">
        <a href="venue.php?<?= htmlspecialchars($venueQuery . $prevWeekDate . $tagsQuery, ENT_QUOTES, 'UTF-8') ?>" rel="prev">Prev<br>week</a>
        <span class="day-nav__day is-active"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
        <a href="venue.php?<?= htmlspecialchars($venueQuery . $nextWeekDate . $tagsQuery, ENT_QUOTES, 'UTF-8') ?>" rel="next">Next<br>week</a>
    </nav>

    <?php if ($hasWeek && $venue !== null): ?>
        <?php include __DIR__ . '/partials/event-filter.php'; ?>
        <?= $venueHtml ?>
    <?php elseif ($venueSlug === ''): ?>
        <p class="error">Missing venue parameter.</p>
    <?php elseif (!$hasWeek): ?>
        <p class="error">No calendar data for week <?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>.</p>
    <?php else: ?>
        <p class="error">Venue not found in this week.</p>
    <?php endif; ?>
</div>

<?php if ($hasWeek && $venue !== null): ?>
<script src="assets/event-filter.js" defer></script>
<?php endif; ?>
</body>
</html>
