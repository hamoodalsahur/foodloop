<?php

// The seller dashboard (mockup c08) and handing food over with the pickup code.

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:00:00');

    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $this->seller = $this->shop->user;
    $this->listing = Listing::factory()->for($this->shop)->create([
        'title' => 'Bakery Surprise Bundle', 'price_sen' => 800, 'original_price_sen' => 2000,
        'quantity_total' => 5, 'quantity_available' => 5,
        'pickup_start' => '2026-11-16 19:00:00', 'pickup_end' => '2026-11-16 21:00:00',
    ]);

    $this->aina = User::factory()->create(['name' => 'Aina Rahman']);
    $this->reservation = reservations()->reserve($this->aina, $this->listing, 2, PaymentMethod::PayNow);
});

it('shows tonight’s numbers, pickups and live listings', function () {
    reservations()->reserve(User::factory()->create(['name' => 'Hafiz Ismail']), $this->listing, 1, PaymentMethod::PayAtPickup);

    $this->actingAs($this->seller)->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Tonight', 'next pickup window opens at 7 PM', 'Waiting for pickup', '2', 'Reservations today', '2'])
        ->assertSeeInOrder(['Pickups', 'Check and collect', $this->reservation->pickup_code, 'Aina R.', 'Bakery Surprise Bundle', '2', '7–9 PM', 'Reserved', 'From 7 PM'])
        ->assertSee('Hafiz I.')
        ->assertSee('3 of 5 reserved')
        ->assertDontSee('Mark collected');
});

it('does not show other shops’ pickups', function () {
    $other = Listing::factory()->create(['title' => 'Someone else’s nasi lemak']);
    reservations()->reserve(User::factory()->create(), $other, 1, PaymentMethod::PayNow);

    $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertDontSee('Someone else’s nasi lemak');
});

it('hands over an order by its code once pickup has started', function () {
    $this->travelTo('2026-11-16 19:10:00');

    $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertSee('Mark collected');

    $this->actingAs($this->seller)->from(route('seller.dashboard'))
        ->post(route('seller.pickups.collect'), ['code' => $this->reservation->pickup_code])
        ->assertRedirect(route('seller.dashboard'))
        ->assertSessionHas('status', 'Collected: Aina R. · Bakery Surprise Bundle × 2. Hand it over.');

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Collected);

    $this->actingAs($this->seller)->get(route('seller.dashboard'))
        ->assertSee('Collected')
        ->assertSeeInOrder(['Sales this week', 'RM16', 'Rescued this month', '2']);
});

it('explains why a code can’t be collected', function (string $time, ?string $code, string $message) {
    $this->travelTo($time);

    $this->actingAs($this->seller)->post(route('seller.pickups.collect'), ['code' => $code ?? $this->reservation->pickup_code])
        ->assertSessionHasErrors(['code' => $message]);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
})->with([
    'too early' => ['2026-11-16 18:30:00', null, 'The pickup window hasn’t opened yet.'],
    'too late' => ['2026-11-16 21:20:00', null, 'The pickup time for this reservation has passed.'],
    'wrong code' => ['2026-11-16 19:10:00', '0000', 'No reservation with that code is waiting at your shop.'],
    'not 4 digits' => ['2026-11-16 19:10:00', '12a', 'A pickup code is 4 digits.'],
]);

it('won’t collect another shop’s order, even with a real code', function () {
    $this->travelTo('2026-11-16 19:10:00');
    $otherShop = Seller::factory()->approved()->create();

    $this->actingAs($otherShop->user)->post(route('seller.pickups.collect'), ['code' => $this->reservation->pickup_code])
        ->assertSessionHasErrors(['code' => 'No reservation with that code is waiting at your shop.']);

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
});

it('lists pickups for the coming days on the pickups page', function () {
    $tomorrow = Listing::factory()->for($this->shop)->create([
        'title' => 'Tomorrow’s kuih', 'pickup_start' => '2026-11-17 17:00:00', 'pickup_end' => '2026-11-17 18:00:00',
    ]);
    $later = Reservation::factory()->for($tomorrow)->create(['pickup_code' => '4321']);

    // The dashboard is about tonight; the listing shows there, its pickup doesn't.
    $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertDontSee('>4321</td>', false);
    $this->actingAs($this->seller)->get(route('seller.pickups.index'))
        ->assertOk()
        ->assertSeeInOrder(['Bakery Surprise Bundle', '4321', 'Tomorrow’s kuih', 'Tomorrow']);
});

it('greets a seller account without a shop instead of failing', function () {
    $this->actingAs(User::factory()->seller()->create())->get(route('seller.dashboard'))->assertOk();
});
