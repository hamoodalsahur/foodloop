<?php

namespace App\Queries;

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use Illuminate\Support\Collection;

/**
 * What a seller's dashboard, pickups and donations pages show. Read-only;
 * nothing here changes data.
 */
class SellerOverview
{
    /** @var Collection<int, Donation>|null */
    private ?Collection $donations = null;

    public function __construct(private Seller $seller) {}

    /** @return array{waiting: int, today: int, salesWeekSen: int, rescuedMonth: int} */
    public function stats(): array
    {
        $collected = ReservationStatus::Collected->value;

        // One pass over this shop's reservations for all four numbers.
        $sums = Reservation::query()
            ->join('listings', 'listings.id', '=', 'reservations.listing_id')
            ->where('listings.seller_id', $this->seller->id)
            ->toBase()
            ->selectRaw('coalesce(sum(case when reservations.status = ? then 1 else 0 end), 0) as waiting', [ReservationStatus::Reserved->value])
            ->selectRaw('coalesce(sum(case when reservations.status != ? and reservations.created_at >= ? then 1 else 0 end), 0) as today', [ReservationStatus::Cancelled->value, today()])
            ->selectRaw('coalesce(sum(case when reservations.status = ? and reservations.collected_at >= ? then reservations.total_sen else 0 end), 0) as sales_week_sen', [$collected, now()->startOfWeek()])
            ->selectRaw('coalesce(sum(case when reservations.status = ? and reservations.collected_at >= ? then reservations.quantity else 0 end), 0) as rescued_month', [$collected, now()->startOfMonth()])
            ->first();

        return [
            'waiting' => (int) $sums->waiting,
            'today' => (int) $sums->today,
            'salesWeekSen' => (int) $sums->sales_week_sen,
            'rescuedMonth' => (int) $sums->rescued_month,
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
     * food-bank offer yet: the "Offer to food bank" list. Only listings that
     * closed because their pickup window ended: one that `demo:refresh` took
     * off sale early has no real leftovers.
     *
     * @return Collection<int, Listing>
     */
    public function leftoversToOffer(): Collection
    {
        return $this->seller->listings()
            ->where('status', ListingStatus::Closed)
            ->where('pickup_end', '>=', now()->subDays(7))
            ->whereColumn('closed_at', '>=', 'pickup_end')
            ->whereDoesntHave('donations')
            ->whereDoesntHave('reservations', fn ($q) => $q->where('status', ReservationStatus::Reserved))
            // The no-show portions come along with the listings (it used to ask twice per listing).
            ->withSum(['reservations as no_show_portions' => fn ($q) => $q->where('status', ReservationStatus::NoShow)], 'quantity')
            ->latest('pickup_end')
            ->get()
            // The same sum as Listing::leftovers(): unsold portions plus no-shows.
            ->each(fn (Listing $l) => $l->setAttribute('leftover_count', $l->quantity_available + (int) $l->no_show_portions))
            ->filter(fn (Listing $l) => $l->leftover_count > 0)
            ->values();
    }

    /** @return Collection<int, Donation> */
    public function donations(): Collection
    {
        // Asked for once per page: the list and "portions given so far" share it.
        return $this->donations ??= Donation::query()
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

    /**
     * What the admins did about reports on this shop's listings in the last
     * two weeks (taken down, or a warning), newest first. One entry per
     * listing and decision, however many customers reported it.
     *
     * @return Collection<int, Report>
     */
    public function adminMessages(): Collection
    {
        return Report::query()
            ->whereNotNull('resolution')
            ->where('resolved_at', '>=', now()->subDays(14))
            ->whereHas('listing', fn ($query) => $query->where('seller_id', $this->seller->id))
            ->with('listing')
            ->latest('resolved_at')
            ->get()
            ->unique(fn (Report $report) => $report->listing_id.'|'.$report->resolution->value.'|'.$report->resolved_at)
            ->values();
    }
}
