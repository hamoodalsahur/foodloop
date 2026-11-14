<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;

// The listing's pickup window runs from 60 to 180 minutes from "now";
// the grace period ends 15 minutes after that.

beforeEach(function () {
    // Stop the clock on a whole minute so "exactly at the cutoff" means exactly.
    $this->travelTo(now()->startOfMinute());
    $this->listing = Listing::factory()->window(60, 180)->create();
    $this->seller = $this->listing->seller;
    $this->reservation = reservations()->reserve(User::factory()->create(), $this->listing, 1, PaymentMethod::PayAtPickup);
    $this->code = $this->reservation->pickup_code;
});

it('collects with the right code inside the window, and takes payment at pickup', function () {
    $this->travel(90)->minutes();

    $collected = reservations()->collect($this->seller, $this->code);

    expect($collected->is($this->reservation))->toBeTrue()
        ->and($collected->status)->toBe(ReservationStatus::Collected)
        ->and($collected->collected_at->equalTo(now()))->toBeTrue()
        ->and($collected->payment_status)->toBe(PaymentStatus::SimulatedPaid)
        ->and($this->listing->fresh()->quantity_available)->toBe(4);
});

it('accepts the code from the first to the last moment allowed', function (int $minutes) {
    $this->travel($minutes)->minutes();

    expect(reservations()->collect($this->seller, $this->code)->status)->toBe(ReservationStatus::Collected);
})->with([
    'the minute the window opens' => [60],
    'the minute the window ends' => [180],
    'the last minute of the grace period' => [195],
]);

it('ignores spaces typed around the code', function () {
    $this->travel(90)->minutes();

    expect(reservations()->collect($this->seller, " {$this->code} ")->status)->toBe(ReservationStatus::Collected);
});

it('refuses before the window opens', function () {
    $this->travel(59)->minutes();

    expectRefusal(fn () => reservations()->collect($this->seller, $this->code), RefusalReason::TooEarly);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
});

it('refuses after the grace period', function () {
    $this->travel(195)->minutes();
    $this->travel(1)->seconds();

    expectRefusal(fn () => reservations()->collect($this->seller, $this->code), RefusalReason::TooLate);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
});

it('rejects a code that belongs to another seller', function () {
    $otherSeller = Seller::factory()->approved()->create();
    $this->travel(90)->minutes();

    expectRefusal(fn () => reservations()->collect($otherSeller, $this->code), RefusalReason::CodeNotFound);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
});

it('finds each shop\'s own reservation when two shops have the same code', function () {
    $otherListing = Listing::factory()->window(60, 180)->create();
    $otherReservation = Reservation::factory()->for($otherListing)->create(['pickup_code' => $this->code]);
    $this->travel(90)->minutes();

    expect(reservations()->collect($otherListing->seller, $this->code)->is($otherReservation))->toBeTrue()
        ->and(reservations()->collect($this->seller, $this->code)->is($this->reservation))->toBeTrue();
});

it('rejects a wrong code', function (string $code) {
    $this->travel(90)->minutes();

    expectRefusal(fn () => reservations()->collect($this->seller, $code), RefusalReason::CodeNotFound);
})->with(['empty' => [''], 'letters' => ['abcd'], 'too long' => ['12345']]);

it('rejects a code that was already used, cancelled or expired', function (Closure $close) {
    $close($this);

    expectRefusal(fn () => reservations()->collect($this->seller, $this->code), RefusalReason::CodeNotFound);
})->with([
    'already collected' => [function ($test) {
        $test->travel(90)->minutes();
        reservations()->collect($test->seller, $test->code);
    }],
    'cancelled' => [function ($test) {
        reservations()->cancel($test->reservation);
        $test->travel(90)->minutes();
    }],
    'no-show' => [function ($test) {
        $test->travel(196)->minutes();
        reservations()->markNoShow($test->reservation);
    }],
]);
