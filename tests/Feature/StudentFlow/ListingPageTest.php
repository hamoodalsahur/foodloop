<?php

// One listing at /listings/{id} (mockups c02 and c07).

use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');

    $shop = Seller::factory()->approved()->certified()->create([
        'business_name' => 'Roti Lane Bakery',
        'lat' => config('foodloop.demo_origin.lat') - 0.6 * 0.009,
        'lng' => config('foodloop.demo_origin.lng'),
    ]);

    $this->listing = Listing::factory()->for($shop)->create([
        'type' => ListingType::Bundle,
        'title' => 'Bakery Surprise Bundle',
        'description' => '4–6 of today’s unsold breads, buns and pastries.',
        'original_price_sen' => 2000,
        'price_sen' => 800,
        'quantity_total' => 5,
        'quantity_available' => 3,
        'pickup_start' => '2026-11-16 19:00:00',
        'pickup_end' => '2026-11-16 21:00:00',
        'halal_status' => 'certified',
        'allergens' => ['egg', 'milk', 'wheat'],
        'published_at' => '2026-11-16 15:00:00',
    ]);

    $this->url = route('listings.show', $this->listing);
});

describe('an open listing', function () {
    it('shows the deal the way the mockup does', function () {
        $this->get($this->url)
            ->assertOk()
            ->assertSeeInOrder([
                'Back to deals', 'Surprise Bundle', 'Example of a typical bundle',
                '60% off', 'You pay RM8', 'instead of RM20',
                'Reservations close in', '02:42:00',
                'Bakery Surprise Bundle', 'Roti Lane Bakery', '0.6 km', 'verified seller',
                'Pickup', '7–9 PM', 'Left', '3 of 5', 'Halal', 'Certified', 'Contains', 'Egg, milk, wheat',
                '4–6 of today’s unsold breads, buns and pastries.',
            ]);
    });

    it('starts the live countdown from the seconds left until reservations close', function () {
        $this->get($this->url)->assertSee('x-data="countdown(9720, true)"', false);
    });

    it('says the day when pickup is not today, and "Not stated" without allergens', function () {
        $this->listing->update(['pickup_start' => '2026-11-17 19:00:00', 'pickup_end' => '2026-11-17 21:00:00', 'allergens' => []]);

        $this->get($this->url)->assertSee('Tomorrow 7–9 PM')->assertSee('Not stated');
    });

    it('explains what "certified" means', function () {
        $this->get($this->url)->assertSee('Halal status is declared by the seller');
    });
});

describe('who sees which reserve button', function () {
    it('asks a guest to log in, and brings them back here afterwards', function () {
        $this->get($this->url)
            ->assertSee('Log in to reserve')
            ->assertSee('Create an account')
            ->assertDontSee('How many?');

        $customer = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => $customer->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect($this->url);
    });

    it('gives a customer the quantity, payment choice and reserve button', function () {
        $this->actingAs(User::factory()->create())->get($this->url)
            ->assertSeeInOrder(['How many?', 'Up to 3 for you', 'How will you pay?', 'Pay now', 'Simulated', 'Pay at pickup', 'Reserve for', 'RM8'])
            ->assertSee('action="'.route('reservations.store', $this->listing).'"', false)
            ->assertSee('quantityPicker({ max: 3, priceSen: 800, initial: 1 })', false);
    });

    it('lowers the most a customer can pick by what they already hold and by what is left', function () {
        $customer = User::factory()->create();
        Reservation::factory()->for($this->listing)->create(['customer_id' => $customer->id, 'quantity' => 2]);

        $this->actingAs($customer)->get($this->url)
            ->assertSee('Up to 1 for you (you already have 2)')
            ->assertSee('max: 1,', false);

        $this->listing->update(['quantity_available' => 2]);
        $this->actingAs(User::factory()->create())->get($this->url)->assertSee('Up to 2 for you');
    });

    it('tells a customer who already holds 3 that this is the most', function () {
        $customer = User::factory()->create();
        Reservation::factory()->for($this->listing)->create(['customer_id' => $customer->id, 'quantity' => 3]);

        $this->actingAs($customer)->get($this->url)
            ->assertSee('You already have 3 of this listing')
            ->assertSee('See your orders')
            ->assertDontSee('Reserve for');
    });

    it('tells a blocked customer until when reserving is paused', function () {
        $customer = User::factory()->create(['name' => 'Daniel']);
        $customer->forceFill(['blocked_until' => '2026-11-21 18:00:00'])->save();

        $this->actingAs($customer)->get($this->url)
            ->assertSee('You can’t reserve until')
            ->assertSee('Sat 21 Nov, 6:00 PM')
            ->assertSee('Reserving is paused')
            ->assertDontSee('How many?');
    });

    it('tells sellers and admins that only customers reserve', function (string $state, string $words) {
        $this->actingAs(User::factory()->{$state}()->create())->get($this->url)
            ->assertSee($words)
            ->assertSee('Only customer accounts can reserve food')
            ->assertDontSee('Reserve for');
    })->with([['seller', 'a seller account'], ['admin', 'an admin account']]);
});

describe('sold out (mockup c07)', function () {
    beforeEach(function () {
        $this->listing->update(['quantity_available' => 0, 'status' => 'sold_out']);
        dealAt(0.4, ['title' => 'Curry puffs × 4', 'pickup_end' => now()->addMinutes(18)]);
        dealAt(0.9, ['title' => 'Iced latte', 'pickup_end' => now()->addMinutes(33)]);
    });

    it('says sold out and suggests what is still available, soonest first', function () {
        $this->get($this->url)
            ->assertOk()
            ->assertSeeInOrder(['All 5 rescued', 'Sold out', 'Bakery Surprise Bundle', 'Still available nearby', 'Curry puffs × 4', 'Iced latte', 'See all deals'])
            ->assertDontSee('Reserve for')
            ->assertDontSee('Log in to reserve')
            ->assertDontSee('Reservations close in');
    });

    it('does not suggest the listing itself, or deals that cannot be reserved', function () {
        Listing::factory()->closed()->create(['title' => 'Old news']);

        $this->get($this->url)->assertDontSee('Old news');
    });
});

it('shows an ended listing as ended, with other deals', function () {
    $this->travelTo('2026-11-16 21:30:00');
    $this->listing->update(['status' => 'closed', 'closed_at' => '2026-11-16 21:00:00']);

    $this->get($this->url)
        ->assertOk()
        ->assertSeeInOrder(['Pickup window ended at 9 PM', 'Ended', 'Still available nearby', 'Nothing else right now'])
        ->assertDontSee('Reserve for');
});

it('treats a live listing whose window has just passed as ended, even before the scheduler closes it', function () {
    $this->travelTo('2026-11-16 21:00:30');

    $this->get($this->url)->assertSee('Ended')->assertDontSee('Reserve for');
});

describe('listings that are not public', function () {
    it('answers "not found" for drafts and paused listings', function (string $status) {
        $this->listing->update(['status' => $status]);

        $this->get($this->url)->assertNotFound();
        $this->actingAs(User::factory()->create())->get($this->url)->assertNotFound();
    })->with(['draft', 'paused']);

    it('still shows them to their own shop and to admins', function () {
        $this->listing->update(['status' => 'paused']);

        $this->actingAs($this->listing->seller->user)->get($this->url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($this->url)->assertOk();
        $this->actingAs(Seller::factory()->approved()->create()->user)->get($this->url)->assertNotFound();
    });
});

it('answers "not found" for a listing that does not exist', function () {
    $this->get('/listings/999999')->assertNotFound()->assertSee('Nothing here.')->assertSee('Back to the deals');
});
