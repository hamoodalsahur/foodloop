<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationRefused;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Listing;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;

/**
 * Making a reservation. This is the pattern every form in the app follows:
 *
 *   1. the form request checks the input and who is asking,
 *   2. the service does the work and enforces the business rules,
 *   3. a refusal comes back as a message next to the form,
 *   4. success redirects to the page that shows the result.
 */
class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Listing $listing, ReservationService $reservations): RedirectResponse
    {
        try {
            $reservation = $reservations->reserve(
                $request->user(),
                $listing,
                $request->integer('quantity'),
                $request->paymentMethod(),
            );
        } catch (ReservationRefused $refused) {
            return redirect()
                ->route('listings.show', $listing)
                ->withInput()
                ->withErrors(['reservation' => $refused->reason->label()]);
        }

        // The booked page says "You're booked" itself, so no extra message is needed.
        return redirect()->route('orders.show', $reservation);
    }
}
