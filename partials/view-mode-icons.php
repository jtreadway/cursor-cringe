<?php

declare(strict_types=1);

/** @var 'day'|'week' $viewMode */
/** @var string $dayHref */
/** @var string $weekHref */
/** @var string $selectedDate */

if (!function_exists('viewModeIconGridMarkup')) {
    function viewModeIconGridMarkup(string $pattern): string
    {
        $cells = [];
        for ($i = 0; $i < 28; $i++) {
            $row = intdiv($i, 7);
            if ($pattern === 'day') {
                $on = $i === 0;
            } elseif ($pattern === 'week') {
                $on = $row === 0;
            } else {
                $on = true;
            }

            $cells[] = '<span class="view-mode-icon__cell' . ($on ? ' is-on' : '') . '"></span>';
        }

        return '<span class="view-mode-icon__grid" aria-hidden="true">' . implode('', $cells) . '</span>';
    }
}

$modes = [
    [
        'view' => 'day',
        'pattern' => 'day',
        'href' => $dayHref,
        'label' => 'Day view',
    ],
    [
        'view' => 'week',
        'pattern' => 'week',
        'href' => $weekHref,
        'label' => 'Week view',
    ],
];

?>
<div class="view-mode-strip" role="group" aria-label="Calendar view">
    <?php foreach ($modes as $mode): ?>
        <?php
        $isActive = $viewMode === $mode['view'];
        $classes = 'view-mode-icon view-mode-icon--' . $mode['pattern'];
        if ($isActive) {
            $classes .= ' is-active';
        }
        ?>
    <a
        href="<?= htmlspecialchars($mode['href'], ENT_QUOTES, 'UTF-8') ?>"
        class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-sync
        data-nav-date="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>"
        data-nav-view="<?= htmlspecialchars($mode['view'], ENT_QUOTES, 'UTF-8') ?>"
        aria-label="<?= htmlspecialchars($mode['label'], ENT_QUOTES, 'UTF-8') ?>"
        <?php if ($isActive): ?>aria-current="page"<?php endif; ?>
    ><?= viewModeIconGridMarkup($mode['pattern']) ?></a>
    <?php endforeach; ?>
</div>
