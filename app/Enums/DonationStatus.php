<?php

namespace App\Enums;

/**
 * What happened to leftovers offered to a food bank.
 */
enum DonationStatus: string
{
    case Offered = 'offered';
    case Collected = 'collected';
    case Declined = 'declined';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Offered => 'Offered',
            self::Collected => 'Collected',
            self::Declined => 'Declined',
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
