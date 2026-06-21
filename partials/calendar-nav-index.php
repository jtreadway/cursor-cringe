<?php

/** @var 'day'|'week' $viewMode */
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

$navQuery = $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . ($filterQuery ?? '');
$dayHref = '?date=' . rawurlencode($selectedDate) . '&view=day' . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . ($filterQuery ?? '');
$weekHref = '?date=' . rawurlencode($selectedDate) . '&view=week' . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery . ($filterQuery ?? '');
$prevHref = '?date=' . rawurlencode($prevWeekDate) . $viewQuery . $navQuery;
$nextHref = '?date=' . rawurlencode($nextWeekDate) . $viewQuery . $navQuery;
$modeHref = $viewMode === 'day' ? $weekHref : $dayHref;
$modeLabel = $viewMode === 'day' ? 'week view' : 'day view';

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
            <span class="calendar-nav__label"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
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
    <nav class="calendar-nav calendar-nav--week" aria-label="Calendar navigation">
        <div class="calendar-nav__week-strip">
            <a
                href="<?= htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="week"
                rel="prev"
            >prev week</a>
            <span class="calendar-nav__label"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
            <a
                href="<?= htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8') ?>"
                class="calendar-nav__week"
                data-nav-sync
                data-nav-date="<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?>"
                data-nav-view="week"
                rel="next"
            >next week</a>
        </div>
    </nav>
<?php endif; ?>
    <p class="calendar-nav__mode">
        <a
            href="<?= htmlspecialchars($modeHref, ENT_QUOTES, 'UTF-8') ?>"
            data-nav-sync
            data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
            data-nav-view="<?= $viewMode === 'day' ? 'week' : 'day' ?>"
        ><?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?></a>
    </p>
</div>
