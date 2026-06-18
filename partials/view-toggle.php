<?php

/** @var 'day'|'week' $viewMode */
/** @var string $selectedDate */

$dayHref = '?date=' . rawurlencode($selectedDate);
$weekHref = '?date=' . rawurlencode($selectedDate) . '&view=week';

?>
<nav class="view-toggle" aria-label="Calendar view">
    <a
        href="<?= htmlspecialchars($dayHref, ENT_QUOTES, 'UTF-8') ?>"
        class="view-toggle__option<?= $viewMode === 'day' ? ' is-active' : '' ?>"
        <?php if ($viewMode === 'day'): ?>aria-current="page"<?php endif; ?>
    >day</a>
    <a
        href="<?= htmlspecialchars($weekHref, ENT_QUOTES, 'UTF-8') ?>"
        class="view-toggle__option<?= $viewMode === 'week' ? ' is-active' : '' ?>"
        <?php if ($viewMode === 'week'): ?>aria-current="page"<?php endif; ?>
    >week</a>
</nav>
