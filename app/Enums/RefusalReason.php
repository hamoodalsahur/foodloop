<?php

namespace App\Enums;

/**
 * Every reason the reservation engine can say "no". The label is the
 * sentence shown to the person; the value is for code and tests.
 */
enum RefusalReason: string
{
    // reserve()
    case OwnListing = 'own_listing';
    case CustomerBlocked = 'customer_blocked';
    case SellerNotApproved = 'seller_not_approved';
    case SoldOut = 'sold_out';
    case ListingNotLive = 'listing_not_live';
    case WindowEnded = 'window_ended';
    case InvalidQuantity = 'invalid_quantity';
    case OverMaxQuantity = 'over_max_quantity';
    case OverListingLimit = 'over_listing_limit';
    case NotEnoughStock = 'not_enough_stock';

    // cancel(), markNoShow()
    case NotReserved = 'not_reserved';
    case AlreadyExpired = 'already_expired';
    case NotExpiredYet = 'not_expired_yet';

    // collect()
    case CodeNotFound = 'code_not_found';
    case TooEarly = 'too_early';
    case TooLate = 'too_late';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::OwnListing => 'You can’t reserve your own listing.',
            self::CustomerBlocked => 'You can’t reserve right now because of missed pickups.',
            self::SellerNotApproved => 'This seller isn’t taking reservations right now.',
            self::SoldOut => 'Sold out. Someone just took the last one.',
            self::ListingNotLive => 'This listing isn’t available right now.',
            self::WindowEnded => 'Reservations for this listing closed when its pickup window opened.',
            self::InvalidQuantity => 'Choose at least 1.',
            self::OverMaxQuantity => 'You can reserve up to '.config('foodloop.max_qty_per_listing').' of each listing.',
            self::OverListingLimit => 'You already have some of this listing. The limit is '.config('foodloop.max_qty_per_listing').' per person.',
            self::NotEnoughStock => 'There aren’t that many left.',
            self::NotReserved => 'This reservation is already closed.',
            self::AlreadyExpired => 'The pickup time has passed, so this can’t be cancelled.',
            self::NotExpiredYet => 'The customer still has time to collect this.',
            self::CodeNotFound => 'No reservation with that code is waiting at your shop.',
            self::TooEarly => 'The pickup window hasn’t opened yet.',
            self::TooLate => 'The pickup time for this reservation has passed.',
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
