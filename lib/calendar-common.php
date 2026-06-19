<?php

declare(strict_types=1);

function mondayForDate(string $ymd): string
{
    $date = DateTimeImmutable::createFromFormat('Ymd', $ymd);
    if ($date === false) {
        throw new InvalidArgumentException('Invalid date');
    }

    if ($date->format('N') !== '1') {
        $date = $date->modify('last Monday');
    }

    return $date->format('Ymd');
}

function weekHeader(string $mondayYmd): string
{
    $monday = DateTimeImmutable::createFromFormat('Ymd', $mondayYmd);
    if ($monday === false) {
        return $mondayYmd;
    }

    $sunday = $monday->modify('+6 days');

    return $monday->format('M j') . ' - ' . $sunday->format('M j, Y');
}

function venueWeekSpan(): int
{
    return 4;
}

function weekRangeHeader(string $firstMondayYmd, string $lastMondayYmd): string
{
    $firstMonday = DateTimeImmutable::createFromFormat('Ymd', $firstMondayYmd);
    $lastMonday = DateTimeImmutable::createFromFormat('Ymd', $lastMondayYmd);

    if ($firstMonday === false || $lastMonday === false) {
        return weekHeader($firstMondayYmd);
    }

    $lastSunday = $lastMonday->modify('+6 days');

    if ($firstMonday->format('Y') === $lastSunday->format('Y')) {
        return $firstMonday->format('M j') . ' - ' . $lastSunday->format('M j, Y');
    }

    return $firstMonday->format('M j, Y') . ' - ' . $lastSunday->format('M j, Y');
}

/**
 * @return list<string>
 */
function availableWeeksAscending(string $weeksDir): array
{
    $weeks = availableWeeks($weeksDir);
    sort($weeks, SORT_STRING);

    return $weeks;
}

/**
 * A aligned window of up to $count weeks containing $selectedDateYmd.
 *
 * @return list<string>
 */
function weekWindowForDate(string $selectedDateYmd, string $weeksDir, ?int $count = null): array
{
    $count ??= venueWeekSpan();
    $available = availableWeeksAscending($weeksDir);

    if ($available === [] || $count < 1) {
        return [];
    }

    $startMonday = mondayForDate($selectedDateYmd);
    $index = count($available) - 1;

    foreach ($available as $i => $ymd) {
        if ($ymd >= $startMonday) {
            $index = $i;
            break;
        }
    }

    $windowStartIndex = intdiv($index, $count) * $count;

    return array_slice($available, $windowStartIndex, $count);
}

function adjacentWeekWindowStart(string $windowStartMonday, string $weeksDir, int $direction, ?int $span = null): ?string
{
    if ($direction === 0) {
        return $windowStartMonday;
    }

    $span ??= venueWeekSpan();
    $available = availableWeeksAscending($weeksDir);
    $index = array_search($windowStartMonday, $available, true);

    if ($index === false) {
        return null;
    }

    $nextIndex = $index + ($direction * $span);

    if ($nextIndex < 0 || $nextIndex >= count($available)) {
        return null;
    }

    return $available[$nextIndex];
}

/**
 * @return list<string>
 */
function availableWeeks(string $weeksDir): array
{
    $files = glob($weeksDir . '/*.json') ?: [];
    $weeks = [];

    foreach ($files as $file) {
        if (preg_match('/(\d{8})\.json$/', $file, $matches)) {
            $weeks[] = $matches[1];
        }
    }

    rsort($weeks, SORT_STRING);

    return $weeks;
}

function showCalendarTimezone(): DateTimeZone
{
    return new DateTimeZone('America/New_York');
}

function calendarToday(?DateTimeZone $tz = null): DateTimeImmutable
{
    $tz ??= showCalendarTimezone();
    $now = new DateTimeImmutable('now', $tz);

    if ((int) $now->format('G') < 4) {
        return $now->modify('-1 day')->setTime(0, 0);
    }

    return $now->setTime(0, 0);
}

/**
 * @param list<string> $available
 */
function defaultWeekMonday(string $weeksDir, array $available): string
{
    $tz = showCalendarTimezone();
    $today = calendarToday($tz);
    $preferredMonday = mondayForDate($today->format('Ymd'));

    if (in_array($preferredMonday, $available, true) && is_readable($weeksDir . '/' . $preferredMonday . '.json')) {
        return $preferredMonday;
    }

    foreach ($available as $weekMonday) {
        $weekStart = DateTimeImmutable::createFromFormat('Ymd', $weekMonday, $tz);
        if ($weekStart === false) {
            continue;
        }

        $weekStart = $weekStart->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days');

        if ($today >= $weekStart && $today <= $weekEnd) {
            return $weekMonday;
        }
    }

    if ($available !== []) {
        return $available[0];
    }

    return $preferredMonday;
}

/**
 * @param array<string, mixed> $weekData
 */
function dayIndexForDate(array $weekData, string $dateYmd): int
{
    foreach ($weekData['days'] as $index => $day) {
        if (($day['date'] ?? '') === $dateYmd) {
            return (int) $index;
        }
    }

    return 0;
}

/**
 * @return list<string>
 */
function parseTagsParam(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    $tags = array_map('trim', explode(',', strtolower($raw)));
    $tags = array_filter($tags, static fn (string $tag): bool => $tag !== '');

    return array_values(array_unique($tags));
}

/**
 * @return string
 */
function parseFindParam(?string $raw): string
{
    if ($raw === null) {
        return '';
    }

    $query = trim($raw);

    return strlen($query) >= 2 ? $query : '';
}

/**
 * @return 'day'|'week'
 */
function parseViewParam(?string $raw): string
{
    return $raw === 'week' ? 'week' : 'day';
}

function parseScopeParam(?string $raw): string
{
    return $raw === 'favorites' ? 'favorites' : 'all';
}

function viewQueryForMode(string $viewMode): string
{
    if ($viewMode === 'week') {
        return '&view=week';
    }

    return '&view=day';
}

function bodyClassForViewMode(string $viewMode): string
{
    return $viewMode === 'week' ? 'view-week' : 'view-day';
}

function scopeQueryForMode(string $scopeMode): string
{
    return $scopeMode === 'favorites' ? '&scope=favorites' : '';
}

function prefsQueryForRequest(): string
{
    return isset($_GET['prefs']) && (string) $_GET['prefs'] === 'neutral' ? '&prefs=neutral' : '';
}

function filterPanelOpenInRequest(): bool
{
    return isset($_GET['filter']) && (string) $_GET['filter'] === 'open';
}

function filterQueryForRequest(): string
{
    return filterPanelOpenInRequest() ? '&filter=open' : '';
}

function preferencesExplicitInRequest(): bool
{
    if (isset($_GET['prefs']) && (string) $_GET['prefs'] === 'neutral') {
        return true;
    }

    return isset($_GET['tags']) || isset($_GET['find']) || isset($_GET['scope']) || isset($_GET['view']);
}

/**
 * @return array{view: 'day'|'week', tags: list<string>, find: string, scope: 'all'|'favorites'}
 */
function loadSavedPreferencesFromCookies(): array
{
    return [
        'view' => parseViewParam(isset($_COOKIE['cringe_view']) ? (string) $_COOKIE['cringe_view'] : null),
        'tags' => parseTagsParam(isset($_COOKIE['cringe_tags']) ? (string) $_COOKIE['cringe_tags'] : null),
        'find' => parseFindParam(isset($_COOKIE['cringe_find']) ? (string) $_COOKIE['cringe_find'] : null),
        'scope' => parseScopeParam(isset($_COOKIE['cringe_scope']) ? (string) $_COOKIE['cringe_scope'] : null),
    ];
}

/**
 * @param array{view: 'day'|'week', tags: list<string>, find: string, scope: 'all'|'favorites'} $saved
 */
function hasSavedPreferencesFromCookies(array $saved): bool
{
    return $saved['view'] !== 'day'
        || $saved['tags'] !== []
        || $saved['find'] !== ''
        || $saved['scope'] === 'favorites';
}

/**
 * @param array{view: 'day'|'week', tags: list<string>, find: string, scope: 'all'|'favorites'} $saved
 */
function buildPreferencesQuery(array $saved): string
{
    $parts = ['view=' . rawurlencode($saved['view'])];

    if ($saved['tags'] !== []) {
        $parts[] = 'tags=' . rawurlencode(implode(',', $saved['tags']));
    }

    if ($saved['find'] !== '') {
        $parts[] = 'find=' . rawurlencode($saved['find']);
    }

    if ($saved['scope'] === 'favorites') {
        $parts[] = 'scope=favorites';
    }

    return implode('&', $parts);
}

function redirectToSavedPreferencesIfNeeded(string $dateYmd): void
{
    if (preferencesExplicitInRequest()) {
        return;
    }

    $saved = loadSavedPreferencesFromCookies();
    if (!hasSavedPreferencesFromCookies($saved)) {
        return;
    }

    header('Location: index.php?date=' . rawurlencode($dateYmd) . '&' . buildPreferencesQuery($saved), true, 302);
    exit;
}

/**
 * @return list<string>
 */
function parseSlugListCookie(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    $slugs = array_map('trim', explode(',', $raw));
    $slugs = array_filter($slugs, static fn (string $slug): bool => $slug !== '');

    return array_values(array_unique($slugs));
}
