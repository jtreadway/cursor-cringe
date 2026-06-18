<?php

declare(strict_types=1);

class EventDateRenderer
{
    public static function renderShort(string $dateShort): string
    {
        if (preg_match('/^(\w{3}) (\d{1,2})$/', $dateShort, $matches)) {
            return '<span class="event-line__date"><span class="event-line__dow">'
                . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8')
                . '</span> <span class="event-line__dom">'
                . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8')
                . '</span>:</span>';
        }

        return '<span class="event-line__date">' . htmlspecialchars($dateShort, ENT_QUOTES, 'UTF-8') . ':</span>';
    }
}
