<?php

/** @var bool $scopeFavorites */
/** @var list<string> $activeTags */
/** @var int $totalEventCount */
/** @var int $totalVenueCount */
/** @var int $favoriteVenueCount */
/** @var array<string, int> $tagCounts */

$scopeFavorites = $scopeFavorites ?? false;
$hideVenueScope = $hideVenueScope ?? false;
$tagCounts = $tagCounts ?? [];
$activeTags = $activeTags ?? [];
$allEventsActive = $activeTags === [];
$tags = array_values(array_unique(array_map('strtolower', $activeTags)));
sort($tags);

$venueClass = $scopeFavorites ? 'event-filter__tag--my-venues' : 'event-filter__tag--all-venues';
$venueCount = $scopeFavorites ? (int) $favoriteVenueCount : (int) $totalVenueCount;
$venueLabel = ($scopeFavorites ? 'my venues' : 'all venues') . ' ' . $venueCount;

if ($allEventsActive) {
    $typesClass = 'event-filter__tag--all';
    $typesLabel = 'all events ' . (int) $totalEventCount;
} else {
    $typesClass = '';
    $shown = array_slice($tags, 0, 3);
    $typesLabel = implode(', ', $shown);
    $extra = count($tags) - count($shown);
    if ($extra > 0) {
        $typesLabel .= ' +' . $extra;
    }
}

?>
<span class="event-filter__summary-pills" data-filter-summary aria-live="polite">
    <?php if (!$hideVenueScope): ?>
    <span
        class="event-filter__summary-pill event-filter__summary-pill--venue event-filter__tag <?= htmlspecialchars($venueClass, ENT_QUOTES, 'UTF-8') ?> is-active"
        data-summary-pill="venue"
    ><?= htmlspecialchars($venueLabel, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <?php if ($hideVenueScope && $tagCounts !== []): ?>
    <span
        class="event-filter__summary-pill event-filter__summary-pill--types event-filter__tag event-filter__tag--all<?= $allEventsActive ? ' is-active' : '' ?>"
        data-summary-pill="types"
    >all events <?= (int) $totalEventCount ?></span>
    <?php foreach ($tagCounts as $tag => $count): ?>
        <?php $tagActive = in_array($tag, $activeTags, true); ?>
    <span
        class="event-filter__summary-pill event-filter__summary-pill--type event-filter__tag<?= $tagActive ? ' is-active' : '' ?>"
        data-summary-pill="type-<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"
    ><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?> <?= (int) $count ?></span>
    <?php endforeach; ?>
    <?php else: ?>
    <span
        class="event-filter__summary-pill event-filter__summary-pill--types event-filter__tag <?= htmlspecialchars($typesClass, ENT_QUOTES, 'UTF-8') ?> is-active"
        data-summary-pill="types"
    ><?= htmlspecialchars($typesLabel, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
</span>
