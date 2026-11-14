<?php

namespace App\Enums;

/**
 * Food category of a listing.
 */
enum ListingCategory: string
{
    case Bakery = 'bakery';
    case Meals = 'meals';
    case Kuih = 'kuih';
    case Drinks = 'drinks';
    case Groceries = 'groceries';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Bakery => 'Bakery',
            self::Meals => 'Meals',
            self::Kuih => 'Kuih',
            self::Drinks => 'Drinks',
            self::Groceries => 'Groceries',
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
