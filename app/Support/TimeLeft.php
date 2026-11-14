<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Turns "how long until X" into the short text used on deals:
 * "18 min left", "1h 20m left", "Ended".
 */
class TimeLeft
{
    /** Minutes at or under which a deal is shown as urgent (marigold highlight). */
    public const URGENT_MINUTES = 35;

    public static function minutes(CarbonInterface $until): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($until, false) / 60));
    }

    public static function label(CarbonInterface $until): string
    {
        $minutes = self::minutes($until);

        return match (true) {
            $minutes === 0 => 'Ended',
            $minutes < 60 => "{$minutes} min left",
            $minutes % 60 === 0 => intdiv($minutes, 60).'h left',
            default => intdiv($minutes, 60).'h '.($minutes % 60).'m left',
        };
    }

    public static function isUrgent(CarbonInterface $until): bool
    {
        $minutes = self::minutes($until);

        return $minutes > 0 && $minutes <= self::URGENT_MINUTES;
    }
}
