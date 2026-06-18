<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/calendar-common.php';
require_once __DIR__ . '/lib/WeekRenderer.php';
require_once __DIR__ . '/lib/CalendarSeo.php';
require_once __DIR__ . '/lib/EventClassifier.php';

$weeksDir = __DIR__ . '/weeks';
$available = availableWeeks($weeksDir);
$tz = showCalendarTimezone();
$today = calendarToday($tz);
$selectedDate = $today->format('Ymd');
$activeTags = parseTagsParam(isset($_GET['tags']) ? (string) $_GET['tags'] : null);

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
$startDayIndex = 0;
$selectedDay = null;
$pageTitle = 'Cringe Calendar POC';
$pageDescription = 'Columbus, Ohio live music calendar.';
$canonicalUrl = CalendarSeo::pageUrl($selectedDate);
$jsonLd = null;
$availableTags = [];
$tagCounts = [];

if ($hasWeek) {
    $weekData = WeekRenderer::loadWeek($weekPath);
    $startDayIndex = dayIndexForDate($weekData, $selectedDate);
    $selectedDay = $weekData['days'][$startDayIndex] ?? $weekData['days'][0];
    $selectedDate = (string) ($selectedDay['date'] ?? $selectedDate);
    $pageTitle = CalendarSeo::pageTitle($selectedDay);
    $pageDescription = CalendarSeo::metaDescription($selectedDay);
    $canonicalUrl = CalendarSeo::pageUrl($selectedDate);
    $jsonLd = CalendarSeo::jsonLd($selectedDay, $canonicalUrl);
    $tagCounts = EventClassifier::tagCountsForDay($selectedDay);
    $availableTags = array_keys($tagCounts);
    $activeTags = array_values(array_intersect($activeTags, $availableTags));

    foreach ($weekData['days'] as $day) {
        $dayPanels .= WeekRenderer::postProcess(WeekRenderer::renderDayPanel($day, $thisWeek));
    }
}

$dayLabels = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
$tagsQuery = $activeTags !== [] ? '&tags=' . rawurlencode(implode(',', $activeTags)) : '';

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
    <link rel="stylesheet" href="assets/week-view.css">
</head>
<body>
<div class="wrap">
    <header>
        <h1>Live Shows Calendar</h1>
        <p class="meta">JSON proof of concept — <?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <nav class="week-nav" aria-label="Week navigation">
        <a href="?date=<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?><?= htmlspecialchars($tagsQuery, ENT_QUOTES, 'UTF-8') ?>" title="Previous week" rel="prev">Prev<br>week</a>
        <?php if ($hasWeek && isset($weekData)): ?>
            <?php foreach ($weekData['days'] as $index => $day): ?>
                <?php
                $isActive = $index === $startDayIndex;
                $dayHref = '?date=' . rawurlencode((string) $day['date']) . $tagsQuery;
                ?>
                <a
                    href="<?= htmlspecialchars($dayHref, ENT_QUOTES, 'UTF-8') ?>"
                    class="day-nav__day<?= $isActive ? ' is-active' : '' ?>"
                    data-day-index="<?= (int) $index ?>"
                    <?php if ($isActive): ?>aria-current="page"<?php endif; ?>
                ><?= htmlspecialchars($dayLabels[$index] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($dayLabels as $label): ?>
                <span class="day-nav__day"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="?date=<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?><?= htmlspecialchars($tagsQuery, ENT_QUOTES, 'UTF-8') ?>" title="Next week" rel="next">Next<br>week</a>
    </nav>

    <?php if ($hasWeek): ?>
        <?php include __DIR__ . '/partials/event-filter.php'; ?>
    <?php endif; ?>

    <p class="pick">Proof of concept: week content is loaded from <code>weeks/<?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>.json</code>. Generate fresh JSON with <code>php generate.php</code> or <a href="generate.php">generate.php</a>. Regression-test with <code>php verify.php</code> or <a href="verify.php">verify.php</a>.</p>

    <?php if ($hasWeek): ?>
        <p class="swipe-hint">Swipe or drag left/right to change day</p>
        <div
            class="week-carousel"
            data-week-carousel
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
    <?php else: ?>
        <p class="error">No JSON found for week <?= htmlspecialchars($thisWeek, ENT_QUOTES, 'UTF-8') ?>. Run <code>php generate.php</code> first.</p>
    <?php endif; ?>
</div>

<?php if ($hasWeek): ?>
<script src="assets/week-carousel.js" defer></script>
<script src="assets/event-filter.js" defer></script>
<?php endif; ?>
</body>
</html>
