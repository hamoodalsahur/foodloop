<?php

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;

// Free cancellation ends 30 minutes before the pickup window starts.
// These listings open in 2 hours, so the cutoff is 90 minutes from "now".

beforeEach(function () {
    // Stop the clock on a whole minute so "exactly at the cutoff" means exactly.
    $this->travelTo(now()->startOfMinute());
    $this->customer = User::factory()->create();
    $this->listing = Listing::factory()->window(120, 240)->create(['quantity_total' => 5, 'quantity_available' => 5]);
    $this->reservation = reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow);
});

it('cancels for free before the cutoff and puts the portions back', function () {
    $this->travel(60)->minutes();

    $cancelled = reservations()->cancel($this->reservation);

    expect($cancelled->status)->toBe(ReservationStatus::Cancelled)
        ->and($cancelled->late_cancel)->toBeFalse()
        ->and($cancelled->cancelled_at->equalTo(now()))->toBeTrue()
        ->and($this->listing->fresh()->quantity_available)->toBe(5)
        ->and($this->customer->fresh()->no_show_strikes)->toBe(0);
});

it('is still free exactly at the cutoff', function () {
    $this->travel(90)->minutes();

    expect(reservations()->cancel($this->reservation)->late_cancel)->toBeFalse()
        ->and($this->customer->fresh()->no_show_strikes)->toBe(0);
});

it('counts a cancel after the cutoff as late: one strike, portions still go back', function () {
    $this->travel(90)->minutes();
    $this->travel(1)->seconds();

    $cancelled = reservations()->cancel($this->reservation);

    expect($cancelled->status)->toBe(ReservationStatus::Cancelled)
        ->and($cancelled->late_cancel)->toBeTrue()
        ->and($this->listing->fresh()->quantity_available)->toBe(5)
        ->and($this->customer->fresh()->no_show_strikes)->toBe(1);
});

it('counts a cancel during the pickup window as late', function () {
    $this->travel(150)->minutes();

    expect(reservations()->cancel($this->reservation)->late_cancel)->toBeTrue()
        ->and($this->customer->fresh()->no_show_strikes)->toBe(1);
});

it('reads the free-cancel time from config/foodloop.php', function () {
    config(['foodloop.free_cancel_minutes' => 100]);

    $this->travel(60)->minutes();

    expect(reservations()->cancel($this->reservation)->late_cancel)->toBeTrue();
});

it('puts a sold-out listing back on sale when someone cancels', function () {
    reservations()->reserve(User::factory()->create(), $this->listing, 3, PaymentMethod::PayNow);
    expect($this->listing->fresh()->status)->toBe(ListingStatus::SoldOut);

    reservations()->cancel($this->reservation);

    expect($this->listing->fresh()->status)->toBe(ListingStatus::Live)
        ->and($this->listing->fresh()->quantity_available)->toBe(2);
});

it('returns the portions but does not reopen a listing that is paused, closed or from a suspended shop', function (Closure $change, ListingStatus $expected) {
    reservations()->reserve(User::factory()->create(), $this->listing, 3, PaymentMethod::PayNow);
    $change($this->listing);

    reservations()->cancel($this->reservation);

    expect($this->listing->fresh()->status)->toBe($expected)
        ->and($this->listing->fresh()->quantity_available)->toBe(2);
})->with([
    'paused' => [fn (Listing $l) => $l->update(['status' => ListingStatus::Paused]), ListingStatus::Paused],
    'closed' => [fn (Listing $l) => $l->update(['status' => ListingStatus::Closed]), ListingStatus::Closed],
    'shop suspended' => [fn (Listing $l) => $l->seller->forceFill(['status' => 'suspended'])->save(), ListingStatus::SoldOut],
]);

it('refuses to cancel a reservation that is not waiting any more', function (string $state) {
    $reservation = Reservation::factory()->{$state}()->create();
    $stock = $reservation->listing->quantity_available;

    expectRefusal(fn () => reservations()->cancel($reservation), RefusalReason::NotReserved);

    expect($reservation->listing->fresh()->quantity_available)->toBe($stock);
})->with(['collected', 'cancelled', 'noShow']);

it('refuses to cancel twice', function () {
    reservations()->cancel($this->reservation);

    expectRefusal(fn () => reservations()->cancel($this->reservation), RefusalReason::NotReserved);

    expect($this->listing->fresh()->quantity_available)->toBe(5);
});

it('refuses to cancel once the grace period is over (that is a no-show)', function () {
    $this->travelTo($this->reservation->expires_at->addSecond());

    expectRefusal(fn () => reservations()->cancel($this->reservation), RefusalReason::AlreadyExpired);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
});
