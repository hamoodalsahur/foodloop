<?php

// End to end over HTTP: a shop posts a deal, a student reserves it,
// the shop hands it over by code, the student sees it collected.

use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;

it('goes from a new listing to a collected order', function () {
    $this->travelTo('2026-11-16 17:00:00');
    $shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $student = User::factory()->create(['name' => 'Aina Rahman']);

    $this->actingAs($shop->user)->post(route('seller.listings.store'), [
        'action' => 'publish', 'type' => 'bundle', 'title' => 'Bakery Surprise Bundle',
        'category' => 'bakery', 'original_price' => '20', 'price' => '8', 'quantity' => 5,
        'pickup_date' => '2026-11-16', 'pickup_start' => '19:00', 'pickup_end' => '21:00',
        'halal_status' => 'muslim_owned', 'allergens' => ['wheat'],
    ])->assertSessionHasNoErrors();
    $listing = Listing::sole();

    $this->actingAs($student)->get('/')->assertSee('Bakery Surprise Bundle');
    $this->actingAs($student)->post(route('reservations.store', $listing), ['quantity' => 2, 'payment_method' => 'pay_at_pickup'])
        ->assertSessionHasNoErrors();
    $reservation = Reservation::sole();

    $this->actingAs($shop->user)->get(route('seller.dashboard'))->assertSee($reservation->pickup_code)->assertSee('Aina R.');

    $this->travelTo('2026-11-16 19:05:00');
    $this->actingAs($shop->user)->post(route('seller.pickups.collect'), ['code' => $reservation->pickup_code])
        ->assertSessionHasNoErrors();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Collected)
        ->and($listing->fresh()->quantity_available)->toBe(3);
    $this->actingAs($student)->get(route('orders.index'))->assertSee('Collected');
});
