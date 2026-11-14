<?php

namespace App\Enums;

/**
 * Halal status shown on every listing (docs/BUILD_PLAN.md §3).
 */
enum HalalStatus: string
{
    case Certified = 'certified';
    case MuslimOwned = 'muslim_owned';
    case NotHalal = 'not_halal';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Certified => 'Halal certified',
            self::MuslimOwned => 'Muslim-owned, not certified',
            self::NotHalal => 'Not halal',
        };
    }

    /** The short form for tight spaces such as the facts box on a listing. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Certified => 'Certified',
            self::MuslimOwned => 'Muslim-owned',
            self::NotHalal => 'Not halal',
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
