<?php

/**
 * Card wrapping week nav + filter panel.
 *
 * @var string $navPartial Path to nav partial (calendar-nav.php or venue-week-nav.php)
 * @var array<string, int> $tagCounts
 * @var list<string> $activeTags
 * @var string $findQuery
 * @var int $totalEventCount
 * @var bool $showFilterActions
 * @var bool $filterPanelOpen
 * @var bool $filtersActive
 */

if (!isset($tagCounts)) {
    $tagCounts = [];
}

$activeTagString = implode(',', $activeTags ?? []);
$findQuery = $findQuery ?? '';
$totalEventCount = $totalEventCount ?? 0;
$filterHidden = $totalEventCount === 0 && $tagCounts === [];
$filterOpen = ($filterPanelOpen ?? false) || filterPanelOpenInRequest();
$filtersActive = $filtersActive ?? false;
$showFilterToggle = true;

if ($filterHidden) {
    if (isset($navPartial) && is_readable($navPartial)) {
        include $navPartial;
    }

    return;
}

$cardClasses = 'calendar-filter-card';
if ($filterOpen) {
    $cardClasses .= ' is-open';
}
if ($filtersActive) {
    $cardClasses .= ' has-active-filters';
}

?>
<div
    class="<?= $cardClasses ?>"
    data-event-filter
    data-active-tags="<?= htmlspecialchars($activeTagString, ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="calendar-filter-card__header">
        <?php include $navPartial; ?>
        <?php include __DIR__ . '/filter-nav-toggle.php'; ?>
    </div>
    <div class="calendar-filter-card__status" data-filter-status>
        <p class="event-filter__summary" data-filter-summary aria-live="polite">
            <?php if ($totalEventCount > 0 && !$filtersActive): ?>
                <?= (int) $totalEventCount ?> events — filter or pick my venues
            <?php endif; ?>
        </p>
        <div class="event-filter__chips" data-filter-chips role="list" hidden></div>
    </div>
    <?php include __DIR__ . '/event-filter-panel.php'; ?>
</div>
