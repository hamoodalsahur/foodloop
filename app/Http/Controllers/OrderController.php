<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationRefused;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Support\Distance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A customer's own reservations: the list, one reservation with its pickup
 * code, and cancelling it. Customers only (see routes/web.php), and the
 * policy makes sure it is their own reservation.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $reservations = $request->user()->reservations()->with('listing.seller')->get();

        [$waiting, $past] = $reservations->partition(fn (Reservation $r) => $r->isReserved());

        return view('orders.index', [
            // Next pickup first.
            'waiting' => $waiting->sortBy(fn (Reservation $r) => $r->listing->pickup_start)->values(),
            // Most recent pickup first.
            'past' => $past->sortByDesc(fn (Reservation $r) => $r->listing->pickup_start)->take(30)->values(),
        ]);
    }

    public function show(Reservation $reservation): View
    {
        Gate::authorize('view', $reservation);

        $reservation->load('listing.seller');
        $listing = $reservation->listing;
        $listing->distanceKm = Distance::toSeller($listing->seller);

        // Which step the pickup is at, for the countdown and the three-step bar.
        $phase = match (true) {
            $reservation->status !== ReservationStatus::Reserved => $reservation->status->value,
            now()->lessThan($listing->pickup_start) => 'before_window',
            now()->lessThanOrEqualTo($listing->pickup_end) => 'window_open',
            default => 'grace',
        };

        return view('orders.show', [
            'reservation' => $reservation,
            'listing' => $listing,
            'phase' => $phase,
        ]);
    }

    public function destroy(Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        try {
            $cancelled = $reservations->cancel($reservation);
        } catch (ReservationRefused $refused) {
            return redirect()
                ->route('orders.show', $reservation)
                ->withErrors(['reservation' => $refused->reason->label()]);
        }

        return redirect()
            ->route('orders.show', $cancelled)
            ->with('status', $cancelled->late_cancel
                ? 'Reservation cancelled. Because it was a late cancel, it counts as a no-show.'
                : 'Reservation cancelled. No strike, and the food is back on sale.');
    }
}
