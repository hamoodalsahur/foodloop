<?php

// The whole student journey on the seeded demo data, the way it will be
// shown in the pitch: sign up, browse, open a deal, reserve, see the code.

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
});

it('shows the mockup deals on the home page, soonest first, with their distances', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            'Curry puffs × 4', 'Kuih Tradisional Tupah', '2.7 km', 'RM3',
            'Iced latte', 'Kopi Saigon Merbok', '1.5 km', 'RM4.50',
            'Bakery Surprise Bundle', 'Bonda Mawar Cake House', '4.5 km', 'RM8',
            'Nasi ayam set (2 pax)', 'Merbok Roasted', '0.1 km', 'RM6.50',
        ], false)
        // Its pickup has already started, so it takes no new reservations.
        ->assertDontSee('Sandwich &amp; pastry pack', false)
        ->assertSeeInOrder(['3', 'Surprise Bundles', 'left'])
        ->assertDontSee('Butter croissants');
});

it('takes a new student from sign-up to a pickup code', function () {
    $bundle = Listing::open()->where('title', 'Bakery Surprise Bundle')->sole();

    // Opens the deal as a guest, is asked to log in, signs up instead…
    $this->get(route('listings.show', $bundle))->assertSee('Log in to reserve');

    $this->post('/register', [
        'name' => 'Siti Aminah',
        'email' => 'siti@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('listings.show', $bundle));

    // …lands back on the deal and reserves two.
    $this->get(route('listings.show', $bundle))->assertSee('Up to 3 for you');

    $this->post(route('reservations.store', $bundle), ['quantity' => 2, 'payment_method' => 'pay_now']);

    $reservation = User::firstWhere('email', 'siti@example.com')->reservations()->sole();

    $this->get(route('orders.show', $reservation))
        ->assertSeeInOrder(['You’re booked.', 'Bonda Mawar Cake House', $reservation->pickup_code, 'RM16 · Paid (simulated)']);

    $this->get('/orders')->assertSeeInOrder(['Waiting for pickup', 'Bakery Surprise Bundle', $reservation->pickup_code]);

    expect($bundle->fresh()->quantity_available)->toBe(1);
});

it('shows Aina her reservation from the mockup and her earlier pickups', function () {
    $aina = User::firstWhere('email', 'aina@foodloop.test');

    $this->actingAs($aina)->get('/orders')
        ->assertSeeInOrder(['Waiting for pickup', 'Bakery Surprise Bundle', '4721', 'Earlier', 'Collected']);

    $this->get(route('orders.show', Reservation::firstWhere('pickup_code', '4721')))
        ->assertSeeInOrder(['You’re booked.', 'Show this code at Bonda Mawar Cake House.', '4721', 'Pickup window opens in', 'RM8 · Paid (simulated)']);
});

it('keeps blocked demo customers from reserving', function () {
    $daniel = User::firstWhere('email', 'daniel@foodloop.test');
    $puffs = Listing::open()->where('title', 'Curry puffs × 4')->sole();

    $this->actingAs($daniel)->get(route('listings.show', $puffs))->assertSee('You can’t reserve until');

    $this->post(route('reservations.store', $puffs), ['quantity' => 1, 'payment_method' => 'pay_now'])
        ->assertSessionHasErrors('reservation');

    expect($daniel->reservations()->where('status', 'reserved')->count())->toBe(0);
});
