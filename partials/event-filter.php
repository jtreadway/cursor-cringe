<?php

/** @var array<string, int> $tagCounts */
/** @var list<string> $activeTags */
/** @var string $findQuery */
/** @var int $totalEventCount */
/** @var int $favoriteEventCount */
/** @var int $totalVenueCount */
/** @var int $favoriteVenueCount */
/** @var bool $showFilterActions */

if (!isset($tagCounts)) {
    $tagCounts = [];
}

$activeTagString = implode(',', $activeTags ?? []);
$findQuery = $findQuery ?? '';
$totalEventCount = $totalEventCount ?? 0;
$favoriteEventCount = $favoriteEventCount ?? 0;
$totalVenueCount = $totalVenueCount ?? 0;
$favoriteVenueCount = $favoriteVenueCount ?? 0;
$scopeMode = $scopeMode ?? 'all';
$allActive = ($activeTags ?? []) === [];
$scopeActive = $scopeMode === 'favorites';
$findPlaceholder = 'Find text (2+ characters)';
$filterHidden = $totalEventCount === 0 && $tagCounts === [];
$filtersActive = !$allActive || $findQuery !== '' || $scopeActive;
$filterOpen = $filtersActive;

if ($filterHidden) {
    return;
}

?>
<div
    class="event-filter<?= $filterOpen ? ' is-open' : '' ?>"
    data-event-filter
    data-active-tags="<?= htmlspecialchars($activeTagString, ENT_QUOTES, 'UTF-8') ?>"
>
    <button
        type="button"
        class="event-filter__toggle"
        data-filter-toggle
        aria-expanded="<?= $filterOpen ? 'true' : 'false' ?>"
        aria-controls="event-filter-panel"
    >
        <span class="event-filter__toggle-label">Filters</span>
        <span class="event-filter__toggle-icon" aria-hidden="true"></span>
    </button>
    <div
        class="event-filter__panel"
        id="event-filter-panel"
        data-filter-panel
        <?php if (!$filterOpen): ?>hidden<?php endif; ?>
    >
        <div class="event-filter__types">
            <div class="event-filter__find-wrap">
                <input
                    type="text"
                    id="event-filter-find"
                    class="event-filter__find"
                    data-filter-find
                    value="<?= htmlspecialchars($findQuery, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="<?= htmlspecialchars($findPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                    enterkeyhint="go"
                >
            </div>
            <div class="event-filter__venue-scope">
                <div class="event-filter__venue-scope-pills" role="group" aria-label="Venue scope">
                    <button
                        type="button"
                        class="event-filter__tag event-filter__tag--all-venues<?= !$scopeActive ? ' is-active' : '' ?>"
                        data-filter-all-venues
                        aria-pressed="<?= !$scopeActive ? 'true' : 'false' ?>"
                        aria-label="all venues, <?= (int) $totalVenueCount ?> venues"
                    >all venues <?= (int) $totalVenueCount ?></button>
                    <button
                        type="button"
                        class="event-filter__tag event-filter__tag--my-venues<?= $scopeActive ? ' is-active' : '' ?>"
                        data-filter-my-venues
                        aria-pressed="<?= $scopeActive ? 'true' : 'false' ?>"
                        aria-label="my venues, <?= (int) $favoriteVenueCount ?> venues"
                    >my venues <?= (int) $favoriteVenueCount ?></button>
                </div>
                <button type="button" class="event-filter__manage-venues" data-manage-venues>manage venues</button>
            </div>
            <div class="event-filter__tags event-filter__type-tags" role="group" aria-label="Event types">
                <button
                    type="button"
                    class="event-filter__tag event-filter__tag--all<?= $allActive ? ' is-active' : '' ?>"
                    data-filter-all
                    aria-pressed="<?= $allActive ? 'true' : 'false' ?>"
                    aria-label="all events, <?= (int) $totalEventCount ?> events"
                >all events <?= (int) $totalEventCount ?></button>
                <?php foreach ($tagCounts as $tag => $count): ?>
                    <?php $isActive = in_array($tag, $activeTags ?? [], true); ?>
                    <button
                        type="button"
                        class="event-filter__tag<?= $isActive ? ' is-active' : '' ?>"
                        data-filter-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"
                        aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
                        aria-label="<?= htmlspecialchars($tag . ', ' . $count . ' events', ENT_QUOTES, 'UTF-8') ?>"
                    ><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?> <?= (int) $count ?></button>
                <?php endforeach; ?>
            </div>
            <?php if ($showFilterActions ?? false): ?>
            <div class="event-filter__actions" data-prefs-actions>
                <button type="button" class="event-filter__action event-filter__action--clear" data-prefs-clear>clear</button>
                <button type="button" class="event-filter__action event-filter__action--save" data-prefs-save>save</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
