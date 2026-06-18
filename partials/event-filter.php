<?php

/** @var array<string, int> $tagCounts */
/** @var list<string> $activeTags */
/** @var string $findQuery */
/** @var int $totalEventCount */

if (!isset($tagCounts)) {
    $tagCounts = [];
}

$activeTagString = implode(',', $activeTags ?? []);
$findQuery = $findQuery ?? '';
$totalEventCount = $totalEventCount ?? 0;
$allActive = ($activeTags ?? []) === [];

?>
<div class="event-filter" data-event-filter data-active-tags="<?= htmlspecialchars($activeTagString, ENT_QUOTES, 'UTF-8') ?>">
    <div class="event-filter__find-wrap">
        <label class="event-filter__label" for="event-filter-find">Find</label>
        <input
            type="text"
            id="event-filter-find"
            class="event-filter__find"
            data-filter-find
            value="<?= htmlspecialchars($findQuery, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Filter listings (2+ characters)"
            autocomplete="off"
            enterkeyhint="go"
        >
    </div>
    <div class="event-filter__types<?= $totalEventCount === 0 && $tagCounts === [] ? ' is-empty' : '' ?>">
        <p class="event-filter__label">Filter by type</p>
        <div class="event-filter__tags event-filter__type-tags" role="group" aria-label="Event type filters">
            <button
                type="button"
                class="event-filter__tag event-filter__tag--all<?= $allActive ? ' is-active' : '' ?>"
                data-filter-all
                aria-pressed="<?= $allActive ? 'true' : 'false' ?>"
                aria-label="all, <?= (int) $totalEventCount ?> events"
            >all <?= (int) $totalEventCount ?></button>
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
    </div>
</div>
