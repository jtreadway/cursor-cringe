<?php

declare(strict_types=1);

class VenueScopeToggle
{
    public static function markup(): string
    {
        return '<p class="venue-scope-toggle" data-venue-scope-toggle>'
            . '<button type="button" class="venue-scope-toggle__control" data-filter-favorites-only aria-pressed="false">'
            . self::heartSvg()
            . '<span class="venue-scope-toggle__text" data-venue-scope-label>show favorite venues only</span> '
            . '(<span data-venue-scope-count>0</span>)'
            . '</button></p>' . "\n";
    }

    private static function heartSvg(): string
    {
        return '<svg class="venue-scope-toggle__heart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<path class="venue-scope-toggle__shape" fill="#cccccc" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
            . '</svg> ';
    }
}
