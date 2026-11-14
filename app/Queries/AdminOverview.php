<?php

namespace App\Queries;

use App\Enums\DonationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The numbers and lists on the admin pages. One place for "what needs a
 * decision", so the overview and the detail pages always agree.
 */
class AdminOverview
{
    /**
     * The four tiles on the overview.
     *
     * @return array{sellersToApprove: int, openReports: int, liveListings: int, mealsThisMonth: int}
     */
    public function stats(): array
    {
        return [
            'sellersToApprove' => Seller::where('status', SellerStatus::Pending)->count(),
            'openReports' => Report::where('status', ReportStatus::Open)->count(),
            // The same test as the deals page: can be reserved right now.
            'liveListings' => Listing::open()->count(),
            'mealsThisMonth' => (int) Reservation::where('status', ReservationStatus::Collected)
                ->where('collected_at', '>=', now()->startOfMonth())
                ->sum('quantity'),
        ];
    }

    /**
     * Shops waiting for a decision, longest wait first.
     *
     * @return Collection<int, Seller>
     */
    public function sellersToApprove(): Collection
    {
        return Seller::where('status', SellerStatus::Pending)->with('user')->oldest()->get();
    }

    /**
     * Every shop, for the Sellers page.
     *
     * @return Collection<int, Seller>
     */
    public function sellers(): Collection
    {
        return Seller::with('user')->withCount('listings')->orderBy('business_name')->get();
    }

    /**
     * Listings with at least one open report, most reported first. Each has
     * its open reports loaded as `openReports`.
     *
     * @return Collection<int, Listing>
     */
    public function reportedListings(): Collection
    {
        $open = fn ($query) => $query->where('status', ReportStatus::Open)->oldest();

        return Listing::whereHas('reports', $open)
            ->with(['seller', 'reports' => $open])
            ->get()
            ->sortBy([
                fn (Listing $a, Listing $b) => $b->reports->count() <=> $a->reports->count(),
                fn (Listing $a, Listing $b) => $a->reports->first()->created_at <=> $b->reports->first()->created_at,
            ])
            ->values();
    }

    /**
     * Reports an admin has already dealt with, newest first.
     *
     * @return Collection<int, Report>
     */
    public function resolvedReports(int $limit = 30): Collection
    {
        return Report::where('status', '!=', ReportStatus::Open)
            ->with(['listing.seller', 'resolver'])
            ->latest('resolved_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Customers who are limited right now or have strikes: limited ones
     * first (longest limit first), then by number of strikes.
     *
     * @return Collection<int, User>
     */
    public function customersWithStrikes(): Collection
    {
        return User::where('role', UserRole::Customer)
            ->where(fn ($query) => $query->where('blocked_until', '>', now())->orWhere('no_show_strikes', '>', 0))
            ->get()
            ->sortBy([
                fn (User $a, User $b) => $b->isBlocked() <=> $a->isBlocked(),
                fn (User $a, User $b) => $b->blocked_until <=> $a->blocked_until,
                fn (User $a, User $b) => $b->no_show_strikes <=> $a->no_show_strikes,
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * Platform totals since the start.
     *
     * @return array{customers: int, approvedSellers: int, mealsRescued: int, savedSen: int, portionsDonated: int}
     */
    public function totals(): array
    {
        $collected = Reservation::where('status', ReservationStatus::Collected)
            ->with('listing:id,original_price_sen,price_sen')
            ->get(['id', 'listing_id', 'quantity']);

        return [
            'customers' => User::where('role', UserRole::Customer)->count(),
            'approvedSellers' => Seller::where('status', SellerStatus::Approved)->count(),
            'mealsRescued' => (int) $collected->sum('quantity'),
            // What customers paid less than the normal price, added up.
            'savedSen' => (int) $collected->sum(fn (Reservation $r) => max(0, $r->listing->saving_sen) * $r->quantity),
            'portionsDonated' => (int) Donation::where('status', '!=', DonationStatus::Declined)->sum('quantity'),
        ];
    }
}
