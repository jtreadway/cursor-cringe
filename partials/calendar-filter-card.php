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
$hideVenueScope = $hideVenueScope ?? false;
$filterAlwaysOpen = $filterAlwaysOpen ?? false;
$filterNavSeparate = $filterNavSeparate ?? false;
$scopeFavorites = $scopeFavorites ?? $scopeActive ?? false;
$filterPanelOpen = $filterOpen;
$findPlaceholder = findFilterPlaceholder();

if ($filterHidden) {
    if (!$filterNavSeparate && isset($navPartial) && is_readable($navPartial)) {
        include $navPartial;
    }

    return;
}

if ($filterAlwaysOpen) {
    $filterOpen = true;
    $filterPanelOpen = true;
}

$cardClasses = 'calendar-filter-card';
if ($filterOpen) {
    $cardClasses .= ' is-open';
}
if ($filtersActive) {
    $cardClasses .= ' has-active-filters';
}
if ($filterAlwaysOpen) {
    $cardClasses .= ' calendar-filter-card--always-open';
}

?>
<div
    class="<?= $cardClasses ?>"
    data-event-filter
    data-active-tags="<?= htmlspecialchars($activeTagString, ENT_QUOTES, 'UTF-8') ?>"
    data-total-event-count="<?= (int) $totalEventCount ?>"
    <?php if ($hideVenueScope): ?>data-hide-venue-scope="true"<?php endif; ?>
    <?php if ($filterAlwaysOpen): ?>data-filter-always-open="true"<?php endif; ?>
>
    <?php if (!$filterNavSeparate): ?>
    <div class="calendar-filter-card__header">
        <?php include $navPartial; ?>
    </div>
    <?php endif; ?>
    <div
        class="calendar-filter-card__filter"
        data-filter-card
        aria-expanded="<?= $filterOpen ? 'true' : 'false' ?>"
        aria-controls="event-filter-panel"
        <?php if (!$filterOpen && !$filterAlwaysOpen): ?>aria-label="<?= $hideVenueScope ? 'Edit event filters' : 'Edit venue and event filters' ?>"<?php endif; ?>
    >
        <?php if (!$filterAlwaysOpen): ?>
        <div class="calendar-filter-card__status" data-filter-status>
            <div class="event-filter__summary-row">
                <div class="event-filter__summary-controls">
                    <label class="event-filter__summary-label" for="event-filter-find">Filter:</label>
                    <input
                        type="text"
                        id="event-filter-find"
                        class="event-filter__find event-filter__find--summary"
                        data-filter-find
                        value="<?= htmlspecialchars(trim($findQuery), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                        enterkeyhint="go"
                    >
                </div>
                <div class="event-filter__summary-pills-wrap">
                    <?php include __DIR__ . '/filter-summary-pills.php'; ?>
                </div>
                <?php include __DIR__ . '/filter-nav-toggle.php'; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="calendar-filter-card__status calendar-filter-card__status--always-open" data-filter-status>
            <div class="event-filter__find-row">
                <label class="event-filter__summary-label" for="event-filter-find">Filter:</label>
                <input
                    type="text"
                    id="event-filter-find"
                    class="event-filter__find event-filter__find--summary"
                    data-filter-find
                    value="<?= htmlspecialchars(trim($findQuery), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                    enterkeyhint="go"
                >
            </div>
        </div>
        <?php endif; ?>
        <?php include __DIR__ . '/event-filter-panel.php'; ?>
    </div>
</div>
