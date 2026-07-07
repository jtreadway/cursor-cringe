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
    aria-label="<?= $filterPanelOpen ? 'Close filters' : 'Show filters' ?>"
    title="<?= $filterPanelOpen ? 'Close filters' : 'Filters' ?>"
>
    <svg class="calendar-filter-card__toggle-icon calendar-filter-card__toggle-icon--filter" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-5.5 6.5v5.5L10 18v-6.5L4 5z"/>
    </svg>
    <svg class="calendar-filter-card__toggle-icon calendar-filter-card__toggle-icon--close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
    </svg>
</button>
