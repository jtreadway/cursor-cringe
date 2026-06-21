<?php

/** @var array<string, int> $tagCounts */
/** @var list<string> $activeTags */
/** @var string $findQuery */
/** @var int $totalEventCount */
/** @var int $favoriteEventCount */
/** @var int $totalVenueCount */
/** @var int $favoriteVenueCount */
/** @var bool $showFilterActions */
/** @var bool $filterOpen */

if (!isset($tagCounts)) {
    $tagCounts = [];
}

$findQuery = $findQuery ?? '';
$totalEventCount = $totalEventCount ?? 0;
$favoriteVenueCount = $favoriteVenueCount ?? 0;
$totalVenueCount = $totalVenueCount ?? 0;
$scopeMode = $scopeMode ?? 'all';
$allActive = ($activeTags ?? []) === [];
$scopeActive = $scopeMode === 'favorites';
$hideVenueScope = $hideVenueScope ?? false;
$filterOpen = $filterOpen ?? false;

?>
<div
    class="event-filter__panel"
    id="event-filter-panel"
    data-filter-panel
    <?php if (!$filterOpen): ?>hidden<?php endif; ?>
>
    <div class="event-filter__types">
        <?php if (!$hideVenueScope): ?>
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
        <?php endif; ?>
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
            <button type="button" class="event-filter__action event-filter__action--save" data-prefs-save>save filters</button>
        </div>
        <?php endif; ?>
    </div>
</div>
