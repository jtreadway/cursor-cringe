<?php

/** @var array<string, int> $tagCounts */
/** @var list<string> $activeTags */

if (!isset($tagCounts)) {
    $tagCounts = [];
}

$activeTagString = implode(',', $activeTags ?? []);

?>
<div class="event-filter<?= $tagCounts === [] ? ' is-empty' : '' ?>" data-event-filter data-active-tags="<?= htmlspecialchars($activeTagString, ENT_QUOTES, 'UTF-8') ?>">
    <p class="event-filter__label">Filter by type</p>
    <div class="event-filter__tags" role="group" aria-label="Event type filters">
        <?php foreach ($tagCounts as $tag => $count): ?>
            <?php
            $isActive = in_array($tag, $activeTags ?? [], true);
            $label = ucwords($tag);
            ?>
            <button
                type="button"
                class="event-filter__tag<?= $isActive ? ' is-active' : '' ?>"
                data-filter-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"
                aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
                aria-label="<?= htmlspecialchars($label . ', ' . $count . ' events', ENT_QUOTES, 'UTF-8') ?>"
            ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= (int) $count ?></button>
        <?php endforeach; ?>
        <button type="button" class="event-filter__clear" data-filter-clear>Clear</button>
    </div>
</div>
