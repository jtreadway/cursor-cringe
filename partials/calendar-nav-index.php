<?php

/** @var 'day'|'week'|'4week' $viewMode */
/** @var string $selectedDate */
/** @var string $prevWeekDate */
/** @var string $nextWeekDate */
/** @var string $weekHeader */
/** @var array<string, mixed>|null $weekData */
/** @var int $startDayIndex */
/** @var list<string> $dayLabels */
/** @var string $tagsQuery */
/** @var string $findQueryParam */
/** @var string $scopeQuery */
/** @var string $prefsQuery */
/** @var string $filterQuery */
/** @var string $viewQuery */
/** @var string|null $prevWindowStart */
/** @var string|null $nextWindowStart */

$navQuery = $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . ($filterQuery ?? '');
$prevHref = '?date=' . rawurlencode($prevWeekDate) . $viewQuery . $navQuery;
$nextHref = '?date=' . rawurlencode($nextWeekDate) . $viewQuery . $navQuery;
$isFourWeekView = $viewMode === '4week';
$prevNavLabel = $isFourWeekView ? 'prev' : 'prev week';
$nextNavLabel = $isFourWeekView ? 'next' : 'next week';

?>
<div class="calendar-filter-card__nav">
<?php if ($viewMode === 'day' && isset($weekData)): ?>
    <nav class="calendar-nav calendar-nav--day" aria-label="Calendar navigation">
        <div class="calendar-nav__week-strip">
            <a
                href="<?= htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="day"
                rel="prev"
            >prev week</a>
            <?php if ($showViewModeStrip ?? false): ?>
            <?php include __DIR__ . '/view-mode-picker.php'; ?>
            <?php else: ?>
            <span class="calendar-nav__label"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <a
                href="<?= htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="day"
                rel="next"
            >next week</a>
        </div>
        <div class="calendar-nav__days" role="group" aria-label="Days this week">
            <?php foreach ($weekData['days'] as $index => $day): ?>
                <?php
                $isActive = $index === ($startDayIndex ?? 0);
                $dayDate = (string) $day['date'];
                $dayLinkHref = '?date=' . rawurlencode($dayDate) . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . ($filterQuery ?? '') . '&view=day';
                ?>
                <a
                    href="<?= htmlspecialchars($dayLinkHref, ENT_QUOTES, 'UTF-8') ?>"
                    class="calendar-nav__day<?= $isActive ? ' is-active' : '' ?>"
                    data-nav-sync
                    data-nav-date="<?= htmlspecialchars($dayDate, ENT_QUOTES, 'UTF-8') ?>"
                    data-nav-view="day"
                    data-day-index="<?= (int) $index ?>"
                    <?php if ($isActive): ?>aria-current="page"<?php endif; ?>
                ><?= htmlspecialchars($dayLabels[$index] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
<?php else: ?>
    <nav class="calendar-nav calendar-nav--<?= $isFourWeekView ? '4week' : 'week' ?>" aria-label="Calendar navigation">
        <div class="calendar-nav__week-strip">
            <?php if ($isFourWeekView && ($prevWindowStart ?? null) === null): ?>
            <span class="calendar-nav__week is-disabled" aria-hidden="true"><?= htmlspecialchars($prevNavLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
            <a
                href="<?= htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="<?= htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8') ?>"
                rel="prev"
            ><?= htmlspecialchars($prevNavLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <?php if ($showViewModeStrip ?? false): ?>
            <?php include __DIR__ . '/view-mode-picker.php'; ?>
            <?php else: ?>
            <span class="calendar-nav__label"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <?php if ($isFourWeekView && ($nextWindowStart ?? null) === null): ?>
            <span class="calendar-nav__week is-disabled" aria-hidden="true"><?= htmlspecialchars($nextNavLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
            <a
                href="<?= htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="<?= htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8') ?>"
                rel="next"
            ><?= htmlspecialchars($nextNavLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
</div>
