<?php

/** @var bool $filterPanelOpen */

$filterPanelOpen = $filterPanelOpen ?? false;

?>
<button
    type="button"
    class="calendar-filter-card__toggle"
    data-filter-toggle
    aria-expanded="<?= $filterPanelOpen ? 'true' : 'false' ?>"
    aria-controls="event-filter-panel"
    aria-label="<?= $filterPanelOpen ? 'Collapse refine options' : 'Expand refine options' ?>"
    title="<?= $filterPanelOpen ? 'Collapse refine options' : 'Expand refine options' ?>"
>
    <svg class="calendar-filter-card__toggle-icon calendar-filter-card__toggle-icon--expand" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
    </svg>
    <svg class="calendar-filter-card__toggle-icon calendar-filter-card__toggle-icon--collapse" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6"/>
    </svg>
</button>
