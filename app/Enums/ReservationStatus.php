<?php

namespace App\Enums;

/**
 * The reservation state machine (docs/BUILD_PLAN.md §5).
 */
enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Collected = 'collected';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Collected => 'Collected',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }

    /**
     * All stored values, e.g. for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
