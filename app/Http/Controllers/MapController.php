<?php

namespace App\Http\Controllers;

use App\Queries\DealsQuery;
use App\Support\DealFilters;
use App\Support\Distance;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The map page: where the shops with deals are, as a SIMPLIFIED drawing
 * (rings of distance around "you are here"), not a real street map. Real
 * maps are out of scope for the prototype (docs/BUILD_PLAN.md §7).
 * Open to everyone, like the deals page.
 */
class MapController extends Controller
{
    /** The drawing covers this many km around "you are here" at most; further shops are only listed. */
    private const MAX_RADIUS_KM = 5;

    public function __invoke(DealsQuery $deals): View
    {
        // Every deal that can be reserved right now, at any distance, halal or not.
        $filters = new DealFilters(halalOnly: false, withinKm: null);
        $listings = $deals->results($deals->inRange($filters), $filters);

        // One entry per shop, nearest first, numbered 1, 2, 3… to match the pins.
        $shops = $listings
            ->groupBy('seller_id')
            ->map(fn (Collection $shopListings) => [
                'seller' => $shopListings->first()->seller,
                'distanceKm' => $shopListings->first()->distanceKm,
                'offset' => Distance::offsetTo($shopListings->first()->seller),
                'listings' => $shopListings->sortBy('pickup_end')->values(),
            ])
            ->sortBy(fn (array $shop) => $shop['distanceKm'] ?? INF)
            ->values()
            ->map(fn (array $shop, int $index) => [...$shop, 'number' => $index + 1]);

        $onMap = $shops->filter(fn (array $shop) => $shop['offset'] !== null && $shop['distanceKm'] <= self::MAX_RADIUS_KM);

        // The outer ring: the smallest of 1, 2 or 5 km that fits every shop drawn.
        $furthest = $onMap->max('distanceKm') ?? 0;
        $radiusKm = collect([1, 2, self::MAX_RADIUS_KM])->first(fn (int $km) => $furthest <= $km * 0.92, self::MAX_RADIUS_KM);

        return view('map.index', [
            'shops' => $shops,
            'onMap' => $onMap,
            'radiusKm' => $radiusKm,
            'rings' => match ($radiusKm) {
                1 => [0.5, 1],
                2 => [0.5, 1, 2],
                default => [1, 2, 5],
            },
            'dealCount' => $listings->count(),
        ]);
    }
}
