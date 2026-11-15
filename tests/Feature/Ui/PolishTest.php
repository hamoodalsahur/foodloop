<?php

// The 15 Nov polish pass: wording that fits the time of day, and buttons that
// show they're busy so a slow connection doesn't lead to a second tap.

use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Support\DayPart;
use Database\Seeders\DemoSeeder;

it('says "today" before 5 PM and "tonight" after', function (string $time, string $word) {
    $this->travelTo("2026-11-16 {$time}");

    expect(DayPart::word())->toBe($word)->and(DayPart::title())->toBe(ucfirst($word));
})->with([['09:15:00', 'today'], ['16:59:00', 'today'], ['17:00:00', 'tonight'], ['23:30:00', 'tonight']]);

it('uses it on the deals page and the seller dashboard', function () {
    $shop = Seller::factory()->approved()->create();

    $this->travelTo('2026-11-16 09:15:00');
    dealAt(0.4, ['type' => 'bundle', 'pickup_start' => now()->addHour(), 'pickup_end' => now()->addHours(2)]);
    $this->get('/')->assertSee('left today')->assertDontSee('left tonight');
    $this->actingAs($shop->user)->get(route('seller.dashboard'))->assertSee('<title>Today', false);

    $this->travelTo('2026-11-16 18:00:00');
    dealAt(0.5, ['type' => 'bundle', 'pickup_start' => now()->addHour(), 'pickup_end' => now()->addHours(2)]);
    auth()->logout();
    $this->get('/')->assertSee('left tonight');
});

it('marks the main buttons with what they say while busy', function () {
    $this->travelTo('2026-11-16 18:00:00');
    $listing = dealAt(0.4);
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('listings.show', $listing))->assertSee('data-busy="Reserving…"', false);

    $reservation = Reservation::factory()->for($listing)->create(['customer_id' => $customer->id]);
    $this->actingAs($customer)->get(route('orders.show', $reservation))->assertSee('data-busy="Cancelling…"', false);

    $this->actingAs($listing->seller->user)->get(route('seller.dashboard'))->assertSee('data-busy="Checking…"', false);
});

it('labels the demo shops’ details as demo data', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(User::firstWhere('email', 'admin@foodloop.test'))->get('/admin')
        ->assertSee('DEMO-')
        ->assertDontSee('[SSM NO.]');
});
