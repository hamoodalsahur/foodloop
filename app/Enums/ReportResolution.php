<?php

namespace App\Enums;

/**
 * What an admin did about a report they acted on (status "actioned").
 * Dismissed reports have no resolution.
 */
enum ReportResolution: string
{
    case ListingHidden = 'listing_hidden';
    case SellerWarned = 'seller_warned';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::ListingHidden => 'Listing taken down',
            self::SellerWarned => 'Seller warned',
        };
    }

    /**
     * All stored values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
