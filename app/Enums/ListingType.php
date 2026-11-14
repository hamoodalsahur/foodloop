<?php

namespace App\Enums;

/**
 * The kind of deal.
 */
enum ListingType: string
{
    case Regular = 'regular';
    case Flash = 'flash';
    case Bundle = 'bundle';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Flash => 'Flash Deal',
            self::Bundle => 'Surprise Bundle',
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
