<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReservationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CollectOrderRequest;
use App\Models\Reservation;
use App\Queries\AdminOrders;
use App\Services\ReservationService;
use App\Support\CustomerName;
use App\Support\OrderTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Orders: every order on FoodLoop across all shops (the audit view),
 * one order with its timeline, and the override that marks a waiting order
 * collected without waiting for its pickup window.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = new AdminOrders($request->query('tab'), $request->query('q'));

        return view('admin.orders.index', [
            'tab' => $orders->tab(),
            'search' => $orders->search(),
            'tabs' => AdminOrders::TABS,
            'orders' => $orders->page(),
            'totals' => $orders->totals(),
            'counts' => $orders->counts(),
        ]);
    }

    public function show(Request $request, Reservation $reservation): View
    {
        Gate::authorize('view', $reservation);

        $reservation->load(['listing.seller', 'customer', 'collectedByAdmin']);

        return view('admin.orders.show', [
            'order' => $reservation,
            'listing' => $reservation->listing,
            'timeline' => OrderTimeline::for($reservation),
            // The confirm step of the override: the same page, asking "are you sure?".
            'confirming' => $request->query('confirm') === 'collect' && $reservation->isReserved(),
        ]);
    }

    /** The admin override. The engine does the work and says no to anything that isn't waiting. */
    public function collect(CollectOrderRequest $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        try {
            $collected = $reservations->collectAsAdmin($reservation, $request->user());
        } catch (ReservationRefused) {
            $status = mb_strtolower($reservation->fresh()->status->label());

            return redirect()->route('admin.orders.show', $reservation)->withErrors([
                'admin' => "Nothing changed: order {$reservation->pickup_code} is already {$status}. Only orders still waiting for pickup can be marked collected.",
            ]);
        }

        return redirect()->route('admin.orders.show', $collected)->with(
            'status',
            "Order {$collected->pickup_code} is marked collected (admin override). "
                .CustomerName::short($collected->customer->name).' and the shop have been told.',
        );
    }
}
