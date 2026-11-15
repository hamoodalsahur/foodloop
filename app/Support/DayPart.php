<?php

namespace App\Support;

/**
 * "Tonight" only reads right in the evening. Before 5 PM the pages say "today".
 *
 *   DayPart::word()   'tonight' or 'today'
 *   DayPart::title()  'Tonight' or 'Today'
 */
class DayPart
{
    public const EVENING_FROM_HOUR = 17;

    public static function word(): string
    {
        return now()->hour >= self::EVENING_FROM_HOUR ? 'tonight' : 'today';
    }

    public static function title(): string
    {
        return ucfirst(self::word());
    }
}
