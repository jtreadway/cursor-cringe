<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/ViewModePicker.php';
require_once __DIR__ . '/../lib/calendar-common.php';

/** @var 'day'|'week'|'4week' $viewMode */
/** @var 'day'|'week'|'4week'|null $viewModeIconsActive */
/** @var string $viewModeVenueSlug */
/** @var string $dayHref */
/** @var string $weekHref */
/** @var string $fourWeekHref */
/** @var string $selectedDate */
/** @var string $weekHeader */

$activeViewMode = $viewModeIconsActive ?? $viewMode;
$viewModeVenueSlug = $viewModeVenueSlug ?? '';
$modes = ViewModePicker::modes($dayHref, $weekHref, $fourWeekHref);
$todayYmd = calendarToday(showCalendarTimezone())->format('Ymd');
$pickerId = 'view-mode-picker-' . substr(md5($weekHeader . $selectedDate), 0, 8);
$panelId = $pickerId . '-panel';

?>
<div
    class="view-mode-picker"
    id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
    data-view-mode-picker
    data-selected-ymd="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
    data-today-ymd="<?= htmlspecialchars($todayYmd, ENT_QUOTES, 'UTF-8') ?>"
    <?php if ($viewModeVenueSlug !== ''): ?>data-view-mode-picker-venue="<?= htmlspecialchars($viewModeVenueSlug, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
>
    <button
        type="button"
        class="view-mode-picker__trigger"
        data-view-mode-picker-trigger
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
        aria-label="<?= htmlspecialchars('Choose date and view, ' . $weekHeader, ENT_QUOTES, 'UTF-8') ?>"
    >
        <span class="view-mode-picker__summary"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
        <svg class="view-mode-picker__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 11h16M8 3v4M16 3v4M6 5h12a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/>
        </svg>
    </button>
    <div
        class="view-mode-picker__panel"
        id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
        data-view-mode-picker-panel
        role="dialog"
        aria-label="Choose date and view"
        hidden
    >
        <div class="view-mode-picker__calendar" data-view-mode-picker-calendar>
            <div class="view-mode-picker__cal-head">
                <button type="button" class="view-mode-picker__cal-prev" data-cal-prev aria-label="Previous month">‹</button>
                <span class="view-mode-picker__cal-label" data-cal-label aria-live="polite"></span>
                <button type="button" class="view-mode-picker__cal-next" data-cal-next aria-label="Next month">›</button>
            </div>
            <div class="view-mode-picker__cal-weekdays" aria-hidden="true">
                <?php foreach (['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'] as $weekday): ?>
                    <span><?= htmlspecialchars($weekday, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
            <div class="view-mode-picker__cal-grid" data-cal-grid role="grid" aria-label="Choose date"></div>
        </div>
        <div class="view-mode-picker__views-row">
            <p class="view-mode-picker__views-label">View:</p>
            <nav class="view-mode-picker__views" aria-label="View">
                <?php foreach ($modes as $index => $mode): ?>
                    <?php
                    $isActive = $activeViewMode === $mode['view'];
                    $classes = 'view-mode-picker__view';
                    if ($isActive) {
                        $classes .= ' is-active';
                    }
                    ?>
                    <?php if ($index > 0): ?><span class="view-mode-picker__sep" aria-hidden="true">|</span><?php endif; ?>
                    <a
                        href="<?= htmlspecialchars($mode['href'], ENT_QUOTES, 'UTF-8') ?>"
                        class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"
                        data-nav-sync
                        data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
                        data-nav-view="<?= htmlspecialchars($mode['view'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($mode['ariaLabel'], ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($mode['view'] === '4week' && $viewModeVenueSlug !== ''): ?>data-nav-venue="<?= htmlspecialchars($viewModeVenueSlug, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                        <?php if ($isActive): ?>aria-current="page"<?php endif; ?>
                    ><?= htmlspecialchars($mode['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</div>
