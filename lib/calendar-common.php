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
