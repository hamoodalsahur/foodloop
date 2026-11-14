<?php

use App\Enums\PaymentMethod;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;

// Window: 60 to 180 minutes from "now". Grace ends at 195 minutes.

beforeEach(function () {
    // Stop the clock on a whole minute so "exactly at the cutoff" means exactly.
    $this->travelTo(now()->startOfMinute());
    $this->customer = User::factory()->create();
    $this->listing = Listing::factory()->window(60, 180)->create(['quantity_total' => 5, 'quantity_available' => 5]);
    $this->reservation = reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow);
});

it('does not mark a no-show before the grace period is over', function (int $minutes) {
    $this->travel($minutes)->minutes();

    expectRefusal(fn () => reservations()->markNoShow($this->reservation), RefusalReason::NotExpiredYet);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved)
        ->and($this->customer->fresh()->no_show_strikes)->toBe(0);
})->with([
    'during the window' => [100],
    'right when the window ends' => [180],
    'at the last minute of grace' => [195],
]);

it('marks a no-show after the grace period: one strike, and the portions become leftovers', function () {
    $this->travel(195)->minutes();
    $this->travel(1)->seconds();

    $noShow = reservations()->markNoShow($this->reservation);

    expect($noShow->status)->toBe(ReservationStatus::NoShow)
        ->and($this->customer->fresh()->no_show_strikes)->toBe(1)
        // Not back on sale…
        ->and($this->listing->fresh()->quantity_available)->toBe(3)
        // …but counted as leftovers: 3 unsold + 2 not collected.
        ->and($this->listing->fresh()->leftovers())->toBe(5);
});

it('reads the grace period from config/foodloop.php', function () {
    config(['foodloop.grace_minutes' => 60]);
    $reservation = reservations()->reserve(User::factory()->create(), $this->listing, 1, PaymentMethod::PayNow);

    $this->travel(200)->minutes();

    expectRefusal(fn () => reservations()->markNoShow($reservation), RefusalReason::NotExpiredYet);
});

it('refuses a no-show for a reservation that was collected or cancelled', function (string $state) {
    $reservation = Reservation::factory()->{$state}()->create();
    $this->travelTo($reservation->expires_at->addMinute());

    expectRefusal(fn () => reservations()->markNoShow($reservation), RefusalReason::NotReserved);

    expect($reservation->customer->fresh()->no_show_strikes)->toBe(0);
})->with(['collected', 'cancelled', 'noShow']);

it('blocks the customer for 7 days at the third strike and restarts the count', function () {
    $this->customer->forceFill(['no_show_strikes' => 2])->save();
    $this->travel(196)->minutes();

    reservations()->markNoShow($this->reservation);

    $customer = $this->customer->fresh();

    expect($customer->no_show_strikes)->toBe(0)
        ->and($customer->blocked_until->equalTo(now()->addDays(7)))->toBeTrue()
        ->and($customer->isBlocked())->toBeTrue();

    $later = Listing::factory()->window(60 * 24 * 10, 60 * 24 * 11)->create();

    expectRefusal(
        fn () => reservations()->reserve($customer, $later, 1, PaymentMethod::PayNow),
        RefusalReason::CustomerBlocked,
    );

    $this->travel(7)->days();
    $this->travel(1)->seconds();

    expect(reservations()->reserve($customer, $later, 1, PaymentMethod::PayNow)->status)
        ->toBe(ReservationStatus::Reserved);
});

it('does not block on the first or second strike', function () {
    $this->travel(196)->minutes();
    reservations()->markNoShow($this->reservation);

    $second = Reservation::factory()->create(['customer_id' => $this->customer->id]);
    $this->travelTo($second->expires_at->addMinute());
    reservations()->markNoShow($second);

    expect($this->customer->fresh()->no_show_strikes)->toBe(2)
        ->and($this->customer->fresh()->isBlocked())->toBeFalse();
});

it('counts a late cancel as the third strike too', function () {
    $this->customer->forceFill(['no_show_strikes' => 2])->save();
    $this->travel(100)->minutes();

    reservations()->cancel($this->reservation);

    expect($this->customer->fresh()->isBlocked())->toBeTrue()
        ->and($this->customer->fresh()->no_show_strikes)->toBe(0)
        ->and($this->customer->fresh()->blocked_until->equalTo(now()->addDays(7)))->toBeTrue();
});

it('reads the strike limit and block length from config/foodloop.php', function () {
    config(['foodloop.strikes_to_block' => 1, 'foodloop.block_days' => 2]);
    $this->travel(196)->minutes();

    reservations()->markNoShow($this->reservation);

    expect($this->customer->fresh()->blocked_until->equalTo(now()->addDays(2)))->toBeTrue();
});

it('never shortens a block that is already running', function () {
    $this->customer->forceFill(['no_show_strikes' => 2, 'blocked_until' => now()->addDays(30)])->save();
    $this->travel(196)->minutes();

    reservations()->markNoShow($this->reservation);

    expect($this->customer->fresh()->blocked_until->equalTo(now()->subMinutes(196)->addDays(30)))->toBeTrue();
});
