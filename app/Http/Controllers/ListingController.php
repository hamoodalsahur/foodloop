<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Reservation;
use App\Queries\DealsQuery;
use App\Support\Distance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One listing: details and the reserve form, or its sold-out / ended state.
 * Open to everyone; reserving needs a customer account.
 */
class ListingController extends Controller
{
    public function show(Request $request, Listing $listing, DealsQuery $deals): View
    {
        // Drafts and paused listings don't exist as far as the public is concerned.
        abort_unless(Gate::forUser($request->user())->allows('view', $listing), 404);

        $listing->load('seller');
        $listing->distanceKm = Distance::toSeller($listing->seller);

        $user = $request->user();

        // A guest who taps "Log in to reserve" comes straight back here afterwards.
        if ($user === null) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        $open = $listing->isOpen() && $listing->quantity_available > 0;

        // How many this customer may still take: the per-listing limit minus what they already hold.
        $held = $user?->isCustomer()
            ? (int) $user->reservations()->where('listing_id', $listing->id)->where('status', '!=', 'cancelled')->sum('quantity')
            : 0;

        return view('listings.show', [
            'listing' => $listing,
            'state' => match (true) {
                $open => 'open',
                $listing->quantity_available < 1 && $listing->pickup_end->isFuture() => 'sold_out',
                default => 'ended',
            },
            'held' => $held,
            'maxQuantity' => max(0, min(config('foodloop.max_qty_per_listing') - $held, $listing->quantity_available)),
            'canReserve' => $user?->can('create', Reservation::class) ?? false,
            'alternatives' => $open ? collect() : $deals->alternativesTo($listing),
        ]);
    }
}
