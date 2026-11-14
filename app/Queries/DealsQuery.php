<?php

namespace App\Queries;

use App\Enums\HalalStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Support\DealFilters;
use App\Support\Distance;
use Illuminate\Support\Collection;

/**
 * Finds the deals for the deals page. One place for "which listings can a
 * customer see, in what order", so the page and its numbers always agree.
 */
class DealsQuery
{
    /**
     * Every deal that can be reserved right now and matches the halal and
     * distance filters, with `distanceKm` filled in on each listing.
     * (Category, type and sorting are applied by results().)
     *
     * @return Collection<int, Listing>
     */
    public function inRange(DealFilters $filters): Collection
    {
        return Listing::query()
            ->open()
            ->with('seller')
            // "Halal only" hides listings marked not halal. Certified and
            // Muslim-owned both stay; each listing page says which one it is.
            ->when($filters->halalOnly, fn ($query) => $query->where('halal_status', '!=', HalalStatus::NotHalal))
            ->get()
            ->each(fn (Listing $listing) => $listing->distanceKm = Distance::toSeller($listing->seller))
            ->when($filters->withinKm !== null, fn (Collection $listings) => $listings->filter(
                fn (Listing $listing) => $listing->distanceKm !== null && $listing->distanceKm <= $filters->withinKm
            ))
            ->values();
    }

    /**
     * The list the customer sees: inRange() narrowed by category and type,
     * in the chosen order.
     *
     * @param  Collection<int, Listing>  $inRange
     * @return Collection<int, Listing>
     */
    public function results(Collection $inRange, DealFilters $filters): Collection
    {
        $listings = $inRange
            ->when($filters->category, fn (Collection $all) => $all->where('category', $filters->category))
            ->when($filters->type, fn (Collection $all) => $all->where('type', $filters->type));

        $sorted = match ($filters->sort) {
            'nearest' => $listings->sortBy([
                fn (Listing $a, Listing $b) => ($a->distanceKm ?? INF) <=> ($b->distanceKm ?? INF),
                ['pickup_start', 'asc'],
            ]),
            'cheapest' => $listings->sortBy([['price_sen', 'asc'], ['pickup_start', 'asc']]),
            'new' => $listings->sortBy([['published_at', 'desc'], ['id', 'desc']]),
            default => $listings->sortBy([['pickup_start', 'asc'], ['id', 'asc']]),
        };

        return $sorted->values();
    }

    /**
     * Other deals to suggest on a sold-out or ended listing page.
     *
     * @return Collection<int, Listing>
     */
    public function alternativesTo(Listing $listing, int $limit = 3): Collection
    {
        return $this->results($this->inRange(new DealFilters(halalOnly: false)), new DealFilters)
            ->reject(fn (Listing $other) => $other->is($listing))
            ->take($limit)
            ->values();
    }

    /** Surprise Bundle portions still available, for the marigold tile. */
    public function bundlesLeft(Collection $inRange): int
    {
        return (int) $inRange->where('type', ListingType::Bundle)->sum('quantity_available');
    }

    /** How many deals stop taking reservations within the hour, for the dark strip. */
    public function closingSoon(Collection $inRange): int
    {
        $deadline = now()->addMinutes(config('foodloop.closing_soon_minutes'));

        return $inRange->filter(fn (Listing $listing) => $listing->reservationsCloseAt()->lessThanOrEqualTo($deadline))->count();
    }
}
