<?php

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * What happens to a listing when its pickup window is over. Used by the
 * `listings:close` command, which runs every minute. Both steps can run
 * again and again without changing anything a second time.
 */
class ListingCloser
{
    /**
     * Step 1: close every published listing whose pickup window has ended,
     * so it can't be reserved any more. Drafts are left alone.
     */
    public function closeEnded(): int
    {
        $closed = 0;

        Listing::query()
            ->whereIn('status', [ListingStatus::Live, ListingStatus::Paused, ListingStatus::SoldOut])
            ->where('pickup_end', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$closed) {
                $closed += DB::transaction(function () use ($id) {
                    $listing = Listing::query()->lockForUpdate()->findOrFail($id);

                    if ($listing->status === ListingStatus::Closed || $listing->pickup_end->isFuture()) {
                        return 0;
                    }

                    $listing->status = ListingStatus::Closed;
                    $listing->closed_at = now();
                    $listing->save();

                    return 1;
                });
            });

        return $closed;
    }

    /**
     * Step 2: offer what is left to the partner food bank, for closed
     * listings where the seller ticked "offer leftovers".
     *
     * This waits until no reservation on the listing is still `reserved`
     * (customers have a grace period after the window ends), because only
     * then is the number of leftovers final: unsold portions plus no-shows.
     * One offer per listing, ever.
     */
    public function offerLeftovers(): int
    {
        $partner = FoodBankPartner::query()->orderBy('id')->first();

        if ($partner === null) {
            return 0;
        }

        $offered = 0;

        Listing::query()
            ->where('status', ListingStatus::Closed)
            ->where('offer_leftovers', true)
            ->whereDoesntHave('donations')
            ->whereDoesntHave('reservations', fn ($query) => $query->where('status', ReservationStatus::Reserved))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use ($partner, &$offered) {
                $offered += DB::transaction(function () use ($id, $partner) {
                    $listing = Listing::query()->lockForUpdate()->findOrFail($id);

                    $stillWaiting = $listing->reservations()->where('status', ReservationStatus::Reserved)->exists();

                    if ($stillWaiting || $listing->donations()->exists() || $listing->leftovers() < 1) {
                        return 0;
                    }

                    $donation = new Donation([
                        'quantity' => $listing->leftovers(),
                        'status' => DonationStatus::Offered,
                    ]);
                    $donation->listing()->associate($listing);
                    $donation->partner()->associate($partner);
                    $donation->save();

                    return 1;
                });
            });

        return $offered;
    }
}
