<?php

declare(strict_types=1);

final class ViewModePicker
{
    public static function isoDate(string $ymd): string
    {
        if (!preg_match('/^\d{8}$/', $ymd)) {
            return '';
        }

        return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
    }

    /**
     * @return list<array{view: string, href: string, label: string, ariaLabel: string}>
     */
    public static function modes(
        string $dayHref,
        string $weekHref,
        string $fourWeekHref
    ): array {
        return [
            [
                'view' => 'day',
                'href' => $dayHref,
                'label' => '1 day',
                'ariaLabel' => '1 day view',
            ],
            [
                'view' => 'week',
                'href' => $weekHref,
                'label' => '1 week',
                'ariaLabel' => '1 week view',
            ],
            [
                'view' => '4week',
                'href' => $fourWeekHref,
                'label' => '4 weeks',
                'ariaLabel' => '4 weeks view',
            ],
        ];
    }
}
