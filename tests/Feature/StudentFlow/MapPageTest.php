<?php

// The map page at /map: a simplified drawing of where the shops with deals are.

use App\Models\Listing;
use App\Models\Seller;
use App\Models\User;
use App\Support\Distance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

it('is open to everyone and switched on in the menu', function () {
    dealAt(0.4);

    $this->get('/map')
        ->assertOk()
        ->assertSee('href="'.route('map').'"', false)
        ->assertDontSee('(coming soon)');

    $this->actingAs(User::factory()->create())->get('/map')->assertOk();
});

it('says plainly that it is a simplified map with demo positions', function () {
    dealAt(0.4);

    $this->get('/map')->assertSee('A simplified map, not a street map.')->assertSee('demo data');
});

it('lists shops with deals nearest first, numbered like their pins, with their deals', function () {
    dealAt(0.9, ['title' => 'Iced latte'], 'Daily Grind Café');
    $near = dealAt(0.3, ['title' => 'Nasi ayam set', 'pickup_end' => now()->addMinutes(58)], 'Kafe Seri Ilmu');
    Listing::factory()->for($near->seller)->create(['title' => 'Teh tarik', 'pickup_end' => now()->addMinutes(20)]);

    $this->get('/map')
        ->assertSeeInOrder([
            '3 deals at 2 shops right now',
            'aria-label="1: Kafe Seri Ilmu, 0.3 km"', 'aria-label="2: Daily Grind Café, 0.9 km"',
            'id="shop-1"', 'Kafe Seri Ilmu', '0.3 km away', '2 deals', 'Teh tarik', 'Nasi ayam set',
            'id="shop-2"', 'Daily Grind Café', '0.9 km away', '1 deal', 'Iced latte',
        ], false)
        ->assertSee('href="#shop-1"', false)
        ->assertSee(route('listings.show', $near), false);
});

it('only shows shops that have something to reserve right now', function () {
    dealAt(0.4, [], 'Open Shop');
    Listing::factory()->for(Seller::factory()->approved()->state(['business_name' => 'Sold Out Shop']))->soldOut()->create();
    Listing::factory()->for(Seller::factory()->approved()->state(['business_name' => 'Closed Shop']))->closed()->create();
    Seller::factory()->approved()->create(['business_name' => 'Shop With Nothing']);
    $suspended = dealAt(0.5, [], 'Suspended Shop');
    $suspended->seller->forceFill(['status' => 'suspended'])->save();

    $this->get('/map')
        ->assertSee('Open Shop')
        ->assertDontSee('Sold Out Shop')->assertDontSee('Closed Shop')->assertDontSee('Shop With Nothing')->assertDontSee('Suspended Shop');
});

it('draws rings that fit the shops', function (float $km, array $rings, string $notThere) {
    dealAt($km);

    $this->get('/map')->assertSeeInOrder(array_map(fn ($ring) => "{$ring} km</text>", $rings), false)->assertDontSee($notThere, false);
})->with([
    'all within 1 km' => [0.4, ['0.5', '1'], '>2 km</text>'],
    'within 2 km' => [1.5, ['0.5', '1', '2'], '>5 km</text>'],
    'within 5 km' => [3.5, ['1', '2', '5'], '>0.5 km</text>'],
]);

it('lists far-away shops and shops without an address, but leaves them off the drawing', function () {
    dealAt(0.4, [], 'Near Shop');
    dealAt(12, [], 'Faraway Shop');
    Listing::factory()->for(Seller::factory()->approved()->state(['business_name' => 'No Address Shop', 'lat' => null, 'lng' => null]))->create();

    $this->get('/map')
        ->assertSee('aria-label="1: Near Shop, 0.4 km"', false)
        ->assertDontSee('aria-label="2: Faraway Shop', false)
        ->assertSeeInOrder(['Near Shop', 'Faraway Shop', '12 km away', 'not on the drawing', 'No Address Shop', 'not on the drawing']);
});

it('places a shop by direction: north is up, east is right', function () {
    $origin = config('foodloop.demo_origin');
    $northEast = Seller::factory()->make(['lat' => $origin['lat'] + 0.009, 'lng' => $origin['lng'] + 0.009]);
    $southWest = Seller::factory()->make(['lat' => $origin['lat'] - 0.0045, 'lng' => $origin['lng'] - 0.018]);

    expect(Distance::offsetTo($northEast)['north'])->toEqualWithDelta(1.0, 0.01)
        ->and(Distance::offsetTo($northEast)['east'])->toEqualWithDelta(1.0, 0.01)
        ->and(Distance::offsetTo($southWest)['north'])->toEqualWithDelta(-0.5, 0.01)
        ->and(Distance::offsetTo($southWest)['east'])->toEqualWithDelta(-2.0, 0.02)
        ->and(Distance::offsetTo(Seller::factory()->make(['lat' => null, 'lng' => null])))->toBeNull();
});

it('has an empty state when no shop has deals', function () {
    $this->get('/map')
        ->assertOk()
        ->assertSee('No shops have deals right now')
        ->assertSee('Quiet right now')
        ->assertDontSee('<svg viewBox="0 0 320 320"', false);
});

it('shows the four demo shops on the seeded data', function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);

    $open = Listing::open()->count();

    $this->get('/map')->assertSeeInOrder([
        "{$open} deals at 4 shops right now",
        'Kafe Seri Ilmu', '0.3 km away',
        'Mak Long’s Kuih', '0.4 km away',
        'Roti Lane Bakery', '0.6 km away',
        'Daily Grind Café', '0.9 km away',
    ]);
});
