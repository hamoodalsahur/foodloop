<?php

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('reserves portions and takes them out of stock', function () {
    $customer = User::factory()->create();
    $listing = Listing::factory()->create(['price_sen' => 650, 'quantity_total' => 5, 'quantity_available' => 5]);

    $reservation = reservations()->reserve($customer, $listing, 2, PaymentMethod::PayAtPickup);

    expect($reservation->status)->toBe(ReservationStatus::Reserved)
        ->and($reservation->quantity)->toBe(2)
        ->and($reservation->total_sen)->toBe(1300)
        ->and($reservation->payment_method)->toBe(PaymentMethod::PayAtPickup)
        ->and($reservation->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($reservation->pickup_code)->toMatch('/^\d{4}$/')
        ->and($reservation->customer->is($customer))->toBeTrue()
        ->and($reservation->expires_at->equalTo($listing->pickup_end->addMinutes(15)))->toBeTrue()
        ->and($listing->fresh()->quantity_available)->toBe(3)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Live);
});

it('marks a pay-now reservation as paid (simulated)', function () {
    $reservation = reservations()->reserve(User::factory()->create(), Listing::factory()->create(), 1, PaymentMethod::PayNow);

    expect($reservation->payment_status)->toBe(PaymentStatus::SimulatedPaid);
});

it('marks the listing sold out when the last portion goes', function () {
    $listing = Listing::factory()->create(['quantity_total' => 2, 'quantity_available' => 2]);

    reservations()->reserve(User::factory()->create(), $listing, 2, PaymentMethod::PayNow);

    expect($listing->fresh()->quantity_available)->toBe(0)
        ->and($listing->fresh()->status)->toBe(ListingStatus::SoldOut);
});

it('stops taking reservations once the pickup window opens (team decision, 14 Nov)', function () {
    $listing = Listing::factory()->window(1, 60)->create();
    $customer = User::factory()->create();

    expect(reservations()->reserve($customer, $listing, 1, PaymentMethod::PayNow)->status)
        ->toBe(ReservationStatus::Reserved);

    $this->travel(1)->minutes();

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing->fresh(), 1, PaymentMethod::PayNow),
        RefusalReason::WindowEnded,
    );
});

it('refuses a listing that is not live', function (string $state, RefusalReason $reason) {
    $listing = Listing::factory()->{$state}()->create();

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing, 1, PaymentMethod::PayNow),
        $reason,
    );

    expect(Reservation::count())->toBe(0);
})->with([
    'draft' => ['draft', RefusalReason::ListingNotLive],
    'paused' => ['paused', RefusalReason::ListingNotLive],
    'closed' => ['closed', RefusalReason::ListingNotLive],
    'sold out' => ['soldOut', RefusalReason::SoldOut],
]);

it('refuses once the pickup window has ended, even if the listing is still marked live', function () {
    $listing = Listing::factory()->window(-120, 30)->create();

    $this->travel(30)->minutes();

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing, 1, PaymentMethod::PayNow),
        RefusalReason::WindowEnded,
    );

    expect($listing->fresh()->quantity_available)->toBe(5);
});

it('refuses a quantity that is not allowed', function (int $quantity, RefusalReason $reason) {
    $listing = Listing::factory()->create(['quantity_total' => 2, 'quantity_available' => 2]);

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing, $quantity, PaymentMethod::PayNow),
        $reason,
    );

    expect($listing->fresh()->quantity_available)->toBe(2)
        ->and(Reservation::count())->toBe(0);
})->with([
    'zero' => [0, RefusalReason::InvalidQuantity],
    'negative' => [-1, RefusalReason::InvalidQuantity],
    'more than the maximum of 3' => [4, RefusalReason::OverMaxQuantity],
    'more than is left' => [3, RefusalReason::NotEnoughStock],
]);

it('reads the maximum quantity from config/foodloop.php', function () {
    config(['foodloop.max_qty_per_listing' => 5]);
    $listing = Listing::factory()->create(['quantity_total' => 10, 'quantity_available' => 10]);

    $reservation = reservations()->reserve(User::factory()->create(), $listing, 5, PaymentMethod::PayNow);

    expect($reservation->quantity)->toBe(5);

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing, 6, PaymentMethod::PayNow),
        RefusalReason::OverMaxQuantity,
    );
});

it('refuses a blocked customer, and lets them back in when the block is over', function () {
    $customer = User::factory()->blocked(days: 7)->create();
    $listing = Listing::factory()->window(60 * 24 * 10, 60 * 24 * 11)->create();

    expectRefusal(
        fn () => reservations()->reserve($customer, $listing, 1, PaymentMethod::PayNow),
        RefusalReason::CustomerBlocked,
    );

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    expect(reservations()->reserve($customer, $listing, 1, PaymentMethod::PayNow)->status)
        ->toBe(ReservationStatus::Reserved);
});

it('refuses a seller reserving their own listing', function () {
    $listing = Listing::factory()->create();

    expectRefusal(
        fn () => reservations()->reserve($listing->seller->user, $listing, 1, PaymentMethod::PayNow),
        RefusalReason::OwnListing,
    );
});

it('refuses when the seller is no longer approved, even if the listing is still live', function (string $status) {
    $listing = Listing::factory()->create();
    $listing->seller->forceFill(['status' => $status])->save();

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $listing, 1, PaymentMethod::PayNow),
        RefusalReason::SellerNotApproved,
    );
})->with(['suspended', 'rejected', 'pending']);

it('does not oversell when two requests both saw the last item', function () {
    $listing = Listing::factory()->create(['quantity_total' => 1, 'quantity_available' => 1]);

    // Two requests each loaded the listing while 1 was left.
    $seenByFirst = Listing::find($listing->id);
    $seenBySecond = Listing::find($listing->id);

    reservations()->reserve(User::factory()->create(), $seenByFirst, 1, PaymentMethod::PayNow);

    expectRefusal(
        fn () => reservations()->reserve(User::factory()->create(), $seenBySecond, 1, PaymentMethod::PayNow),
        RefusalReason::SoldOut,
    );

    expect(Reservation::count())->toBe(1)
        ->and($listing->fresh()->quantity_available)->toBe(0);
});

it('checks the customer\'s block again at the moment of reserving', function () {
    $customer = User::factory()->create();
    $loadedEarlier = User::find($customer->id);

    $customer->forceFill(['blocked_until' => now()->addDays(7)])->save();

    expectRefusal(
        fn () => reservations()->reserve($loadedEarlier, Listing::factory()->create(), 1, PaymentMethod::PayNow),
        RefusalReason::CustomerBlocked,
    );
});

it('gives a pickup code that no other waiting reservation at the shop has', function () {
    $seller = Seller::factory()->approved()->create();
    $busy = Listing::factory()->for($seller)->create(['quantity_total' => 20000, 'quantity_available' => 10001]);
    $other = Listing::factory()->for($seller)->create();
    $customer = User::factory()->create();

    // Every code except 0427 is already waiting at this shop.
    $rows = collect(range(0, 9999))->reject(fn (int $n) => $n === 427)->map(fn (int $n) => [
        'listing_id' => $busy->id,
        'customer_id' => $customer->id,
        'quantity' => 1,
        'total_sen' => 800,
        'payment_method' => 'pay_now',
        'payment_status' => 'simulated_paid',
        'pickup_code' => str_pad((string) $n, 4, '0', STR_PAD_LEFT),
        'status' => 'reserved',
        'expires_at' => $busy->pickup_end,
    ]);
    $rows->chunk(1000)->each(fn ($chunk) => DB::table('reservations')->insert($chunk->all()));

    // A reservation on another listing of the same shop gets the one free code.
    $reservation = reservations()->reserve(User::factory()->create(), $other, 1, PaymentMethod::PayNow);

    expect($reservation->pickup_code)->toBe('0427');

    // Now every code is taken: the next reservation fails and leaves the stock alone.
    expect(fn () => reservations()->reserve(User::factory()->create(), $other, 1, PaymentMethod::PayNow))
        ->toThrow(RuntimeException::class, 'every pickup code');

    expect($other->fresh()->quantity_available)->toBe(4);
});
