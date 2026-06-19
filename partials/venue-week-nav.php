<?php

/** @var string $prevVenueHref */
/** @var string $nextVenueHref */
/** @var string $prevWeekDate */
/** @var string $nextWeekDate */
/** @var string $venueSlug */
/** @var string $weekHeader */
/** @var string|null $prevWindowStart */
/** @var string|null $nextWindowStart */

?>
<nav class="calendar-nav" aria-label="Week navigation">
    <?php if ($prevWindowStart !== null): ?>
    <a
        href="<?= htmlspecialchars($prevVenueHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__week"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($prevWeekDate, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-venue="<?= htmlspecialchars($venueSlug, ENT_QUOTES, 'UTF-8') ?>"
        rel="prev"
    >prev</a>
    <?php else: ?>
    <span class="calendar-nav__week is-disabled" aria-hidden="true">prev</span>
    <?php endif; ?>
    <span class="calendar-nav__layout is-active"><?= htmlspecialchars($weekHeader, ENT_QUOTES, 'UTF-8') ?></span>
    <?php if ($nextWindowStart !== null): ?>
    <a
        href="<?= htmlspecialchars($nextVenueHref, ENT_QUOTES, 'UTF-8') ?>"
        class="calendar-nav__week"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($nextWeekDate, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-venue="<?= htmlspecialchars($venueSlug, ENT_QUOTES, 'UTF-8') ?>"
        rel="next"
    >next</a>
    <?php else: ?>
    <span class="calendar-nav__week is-disabled" aria-hidden="true">next</span>
    <?php endif; ?>
</nav>
