<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\ReservationRefused;
use App\Http\Controllers\Controller;
use App\Queries\SellerOverview;
use App\Services\ReservationService;
use App\Support\CustomerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handing food over: the full pickups list, and the 4-digit code check.
 * Only codes waiting at the signed-in seller's own shop are ever found
 * (ReservationService::collect).
 */
class PickupController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->seller === null, 404);

        return view('seller.pickups', [
            'pickups' => (new SellerOverview($request->user()->seller))->pickups(includeUpcomingDays: true),
        ]);
    }

    public function collect(Request $request, ReservationService $reservations): RedirectResponse
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);

        $code = $request->validate(
            ['code' => ['required', 'digits:4']],
            ['code.required' => 'Type the customer’s 4-digit code.', 'code.digits' => 'A pickup code is 4 digits.'],
        )['code'];

        try {
            $reservation = $reservations->collect($shop, $code);
        } catch (ReservationRefused $refused) {
            return back()->withInput()->withErrors(['code' => $refused->reason->label()]);
        }

        $reservation->load('listing', 'customer');

        return back()->with('status', sprintf(
            'Collected: %s · %s × %d. Hand it over.',
            CustomerName::short($reservation->customer->name),
            $reservation->listing->title,
            $reservation->quantity,
        ));
    }
}
