<?php

namespace App\Enums;

/**
 * Why a customer reported a listing.
 */
enum ReportReason: string
{
    case HalalInfo = 'halal_info';
    case FoodQuality = 'food_quality';
    case WrongDescription = 'wrong_description';
    case Other = 'other';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::HalalInfo => 'Halal information',
            self::FoodQuality => 'Food quality',
            self::WrongDescription => 'Wrong description',
            self::Other => 'Other',
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
