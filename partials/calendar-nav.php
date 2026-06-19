<?php

/** @var 'day'|'week' $viewMode */
/** @var string $selectedDate */
/** @var string $prevWeekDate */
/** @var string $nextWeekDate */
/** @var string $tagsQuery */
/** @var string $findQueryParam */
/** @var string $scopeQuery */
/** @var string $prefsQuery */
/** @var string $viewQuery */

$navQuery = $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery;
$dayHref = '?date=' . rawurlencode($selectedDate) . '&view=day' . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery;
$weekHref = '?date=' . rawurlencode($selectedDate) . '&view=week' . $tagsQuery . $findQueryParam . $scopeQuery . $prefsQuery;
$prevHref = '?date=' . rawurlencode($prevWeekDate) . $viewQuery . $navQuery;
$nextHref = '?date=' . rawurlencode($nextWeekDate) . $viewQuery . $navQuery;

?>
<nav class="calendar-nav" aria-label="Calendar navigation">
    <a
        href="<?= htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__week"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?>"
        rel="prev"
    >prev week</a>
    <a
        href="<?= htmlspecialchars($dayHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__layout<?= $viewMode === 'day' ? ' is-active' : '' ?>"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-view="day"
        <?php if ($viewMode === 'day'): ?>aria-current="page"<?php endif; ?>
    >by day</a>
    <a
        href="<?= htmlspecialchars($weekHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__layout<?= $viewMode === 'week' ? ' is-active' : '' ?>"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-view="week"
        <?php if ($viewMode === 'week'): ?>aria-current="page"<?php endif; ?>
    >by week</a>
    <a
        href="<?= htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__week"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?>"
        rel="next"
    >next week</a>
</nav>
