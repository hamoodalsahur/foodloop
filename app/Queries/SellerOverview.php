<?php

namespace App\Queries;

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use Illuminate\Support\Collection;

/**
 * What a seller's dashboard, pickups and donations pages show. Read-only;
 * nothing here changes data.
 */
class SellerOverview
{
    public function __construct(private Seller $seller) {}

    /** @return array{waiting: int, today: int, salesWeekSen: int, rescuedMonth: int} */
    public function stats(): array
    {
        $mine = Reservation::query()->whereHas('listing', fn ($q) => $q->where('seller_id', $this->seller->id));
        $collected = (clone $mine)->where('status', ReservationStatus::Collected);

        return [
            'waiting' => (clone $mine)->where('status', ReservationStatus::Reserved)->count(),
            'today' => (clone $mine)->where('status', '!=', ReservationStatus::Cancelled)->where('created_at', '>=', today())->count(),
            'salesWeekSen' => (int) (clone $collected)->where('collected_at', '>=', now()->startOfWeek())->sum('total_sen'),
            'rescuedMonth' => (int) (clone $collected)->where('collected_at', '>=', now()->startOfMonth())->sum('quantity'),
        ];
    }

    /**
     * Today's pickups: everything still waiting, plus today's collected,
     * missed and cancelled ones. Waiting first (soonest pickup first).
     *
     * @return Collection<int, Reservation>
     */
    public function pickups(bool $includeUpcomingDays = false): Collection
    {
        $rows = Reservation::query()
            ->with(['listing', 'customer'])
            ->whereHas('listing', function ($q) use ($includeUpcomingDays) {
                $q->where('seller_id', $this->seller->id)->where('pickup_end', '>=', today());
                if (! $includeUpcomingDays) {
                    $q->where('pickup_start', '<', today()->addDay());
                }
            })
            ->get();

        return $rows->sortBy([
            fn (Reservation $a, Reservation $b) => ($b->status === ReservationStatus::Reserved) <=> ($a->status === ReservationStatus::Reserved),
            fn (Reservation $a, Reservation $b) => $a->listing->pickup_start <=> $b->listing->pickup_start,
            fn (Reservation $a, Reservation $b) => $a->id <=> $b->id,
        ])->values();
    }

    /**
     * Listings on (or about to come off) the deals page: live, sold out and
     * paused ones whose pickup hasn't ended. Each gets `taken` (portions
     * reserved or collected).
     *
     * @return Collection<int, Listing>
     */
    public function activeListings(): Collection
    {
        return $this->seller->listings()
            ->whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut, ListingStatus::Paused])
            ->where('pickup_end', '>', now())
            ->orderBy('pickup_start')
            ->get()
            ->each(fn (Listing $l) => $l->setAttribute('taken', $l->quantity_total - $l->quantity_available));
    }

    /**
     * Closed listings from the last 7 days with portions left over and no
     * food-bank offer yet: the "Offer to food bank" list.
     *
     * @return Collection<int, Listing>
     */
    public function leftoversToOffer(): Collection
    {
        return $this->seller->listings()
            ->where('status', ListingStatus::Closed)
            ->where('pickup_end', '>=', now()->subDays(7))
            ->whereDoesntHave('donations')
            ->whereDoesntHave('reservations', fn ($q) => $q->where('status', ReservationStatus::Reserved))
            ->latest('pickup_end')
            ->get()
            ->filter(fn (Listing $l) => $l->leftovers() > 0)
            ->each(fn (Listing $l) => $l->setAttribute('leftover_count', $l->leftovers()))
            ->values();
    }

    /** @return Collection<int, Donation> */
    public function donations(): Collection
    {
        return Donation::query()
            ->with(['listing', 'partner'])
            ->whereHas('listing', fn ($q) => $q->where('seller_id', $this->seller->id))
            ->latest()
            ->get();
    }

    /** Portions given to the food bank (offered or collected, not declined). */
    public function donatedPortions(): int
    {
        return (int) $this->donations()
            ->where('status', '!=', DonationStatus::Declined)
            ->sum('quantity');
    }
}
