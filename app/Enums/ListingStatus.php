<?php

namespace App\Enums;

/**
 * Where a listing is in its life.
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    // Taken off the deals page by an admin after a report. The seller can't bring it back.
    case Hidden = 'hidden';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
            self::Paused => 'Paused',
            self::SoldOut => 'Sold out',
            self::Closed => 'Closed',
            self::Hidden => 'Hidden by FoodLoop',
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
