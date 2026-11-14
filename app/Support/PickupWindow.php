<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Writes a pickup window the way the screens show it:
 * "7–9 PM", "11 AM–1 PM", "7:30–9 PM", and with the day: "Today 7–9 PM".
 */
class PickupWindow
{
    /** "7–9 PM" */
    public static function times(CarbonInterface $start, CarbonInterface $end): string
    {
        $sameHalf = $start->format('A') === $end->format('A') && $start->isSameDay($end);

        return self::time($start, withMeridiem: ! $sameHalf).'–'.self::time($end);
    }

    /** "Today 7–9 PM", "Tomorrow 7–9 PM", "Mon 16 Nov 7–9 PM" */
    public static function withDay(CarbonInterface $start, CarbonInterface $end): string
    {
        return self::day($start).' '.self::times($start, $end);
    }

    /** "6:30 PM", "7 PM" */
    public static function time(CarbonInterface $moment, bool $withMeridiem = true): string
    {
        $format = $moment->minute === 0 ? 'g' : 'g:i';

        return $moment->format($withMeridiem ? $format.' A' : $format);
    }

    /** "Today", "Tomorrow", "Yesterday" or "Mon 16 Nov" */
    public static function day(CarbonInterface $moment): string
    {
        return match (true) {
            $moment->isToday() => 'Today',
            $moment->isTomorrow() => 'Tomorrow',
            $moment->isYesterday() => 'Yesterday',
            default => $moment->format('D j M'),
        };
    }
}
