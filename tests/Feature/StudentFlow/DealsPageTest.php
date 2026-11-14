<?php

// The deals page at "/" (mockups c01, c05, c06).

use App\Enums\HalalStatus;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\User;
use App\Queries\DealsQuery;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

it('is open to everyone and says clearly that this is demo data with simulated payments', function () {
    dealAt(0.4, ['title' => 'Curry puffs × 4']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Curry puffs × 4')
        ->assertSee('Log in')
        ->assertSee('Payments are simulated')
        ->assertSee('demo data');
});

it('lists deals closing soonest first, with shop, distance, time left and price', function () {
    dealAt(0.9, ['title' => 'Iced latte', 'price_sen' => 450, 'original_price_sen' => 900, 'pickup_end' => now()->addMinutes(33)], 'Daily Grind Café');
    dealAt(0.4, ['title' => 'Curry puffs × 4', 'price_sen' => 300, 'original_price_sen' => 600, 'pickup_end' => now()->addMinutes(18)], 'Mak Long’s Kuih');
    dealAt(0.3, ['title' => 'Nasi ayam set', 'pickup_end' => now()->addMinutes(58)]);

    $this->get('/')
        ->assertSeeInOrder(['18 min left', 'Curry puffs × 4', 'Mak Long’s Kuih', '0.4 km', 'RM3', '33 min left', 'Iced latte', 'Daily Grind Café', '0.9 km', 'RM4.50', 'Nasi ayam set'])
        ->assertSee(route('listings.show', Listing::firstWhere('title', 'Iced latte')), false);
});

it('only shows deals that can be reserved right now', function () {
    dealAt(0.4, ['title' => 'Open deal']);
    Listing::factory()->draft()->create(['title' => 'A draft']);
    Listing::factory()->paused()->create(['title' => 'A paused one']);
    Listing::factory()->soldOut()->create(['title' => 'A sold-out one']);
    Listing::factory()->closed()->create(['title' => 'A closed one']);
    dealAt(0.4, ['title' => 'Window just ended', 'pickup_start' => now()->subHours(2), 'pickup_end' => now()]);

    $suspended = dealAt(0.4, ['title' => 'From a suspended shop']);
    $suspended->seller->forceFill(['status' => 'suspended'])->save();

    $this->get('/?within=any&halal=0')
        ->assertSee('Open deal')
        ->assertDontSee('A draft')
        ->assertDontSee('A paused one')
        ->assertDontSee('A sold-out one')
        ->assertDontSee('A closed one')
        ->assertDontSee('Window just ended')
        ->assertDontSee('From a suspended shop');
});

describe('sorting', function () {
    beforeEach(function () {
        dealAt(1.5, ['title' => 'Far cheap new', 'price_sen' => 200, 'pickup_end' => now()->addMinutes(90), 'published_at' => now()->subMinutes(5)]);
        dealAt(0.2, ['title' => 'Near pricey old', 'price_sen' => 900, 'pickup_end' => now()->addMinutes(60), 'published_at' => now()->subHours(5)]);
        dealAt(0.8, ['title' => 'Middle closing', 'price_sen' => 500, 'pickup_end' => now()->addMinutes(20), 'published_at' => now()->subHours(2)]);
    });

    it('orders the list by the chosen tab', function (string $query, array $order) {
        $this->get('/'.$query)->assertSeeInOrder($order);
    })->with([
        'closing soon (default)' => ['', ['Middle closing', 'Near pricey old', 'Far cheap new']],
        'nearest' => ['?sort=nearest', ['Near pricey old', 'Middle closing', 'Far cheap new']],
        'cheapest' => ['?sort=cheapest', ['Far cheap new', 'Middle closing', 'Near pricey old']],
        'new' => ['?sort=new', ['Far cheap new', 'Middle closing', 'Near pricey old']],
    ]);

    it('marks the chosen tab', function () {
        $this->get('/?sort=cheapest')
            ->assertSeeInOrder(['aria-current="true"', 'Cheapest'], false)
            ->assertSeeInOrder(['Closing soon', 'Nearest', 'Cheapest', 'New']);
    });
});

describe('halal filter', function () {
    beforeEach(function () {
        Listing::factory()->certified()->create(['title' => 'Certified bread'])->seller->update(config('foodloop.demo_origin'));
        dealAt(0.4, ['title' => 'Muslim-owned kuih', 'halal_status' => HalalStatus::MuslimOwned]);

        $notHalalShop = Seller::factory()->approved()->notHalal()->create(config('foodloop.demo_origin'));
        Listing::factory()->for($notHalalShop)->create(['title' => 'Pork bun', 'halal_status' => HalalStatus::NotHalal]);
    });

    it('is on by default and hides listings marked not halal', function () {
        $this->get('/')
            ->assertSee('Certified bread')
            ->assertSee('Muslim-owned kuih')
            ->assertDontSee('Pork bun')
            ->assertSeeInOrder(['Halal only', 'On']);
    });

    it('shows everything when turned off', function () {
        $this->get('/?halal=0')
            ->assertSee('Pork bun')
            ->assertSee('Certified bread')
            ->assertSeeInOrder(['Halal only', 'Off']);
    });
});

it('filters by food category and marks the chosen chip', function () {
    dealAt(0.4, ['title' => 'Iced latte', 'category' => 'drinks']);
    dealAt(0.4, ['title' => 'Croissants', 'category' => 'bakery']);

    $this->get('/?category=drinks')->assertSee('Iced latte')->assertDontSee('Croissants');
    $this->get('/')->assertSee('Iced latte')->assertSee('Croissants')->assertSeeInOrder(['All food', 'Bakery', 'Meals', 'Kuih', 'Drinks', 'Groceries']);
});

describe('distance filter', function () {
    beforeEach(function () {
        dealAt(0.5, ['title' => 'Round the corner']);
        dealAt(3.5, ['title' => 'Across campus']);
        dealAt(8, ['title' => 'In town']);
        Listing::factory()->for(Seller::factory()->approved()->state(['lat' => null, 'lng' => null]))->create(['title' => 'No address yet']);
    });

    it('shows deals within 2 km by default', function () {
        $this->get('/')
            ->assertSee('Round the corner')->assertDontSee('Across campus')->assertDontSee('In town')
            ->assertSee('Within 2 km');
    });

    it('can widen to 5 km or any distance', function () {
        $this->get('/?within=5')->assertSee('Round the corner')->assertSee('Across campus')->assertDontSee('In town');

        $this->get('/?within=any')
            ->assertSee('Round the corner')->assertSee('Across campus')->assertSee('In town')
            ->assertSee('No address yet')
            ->assertSee('Any distance');
    });
});

describe('the tiles and the strip', function () {
    it('counts the Surprise Bundle portions left and can show only bundles', function () {
        dealAt(0.4, ['title' => 'Bakery bundle', 'type' => 'bundle', 'quantity_available' => 3]);
        dealAt(0.6, ['title' => 'Veg bundle', 'type' => 'bundle', 'quantity_available' => 5]);
        dealAt(0.6, ['title' => 'Plain latte', 'quantity_available' => 4]);
        dealAt(9, ['title' => 'Faraway bundle', 'type' => 'bundle', 'quantity_available' => 7]);

        $this->get('/')->assertSeeInOrder(['Surprise Bundles', '8', 'left tonight']);

        $this->get('/?type=bundle')
            ->assertSee('Bakery bundle')->assertSee('Veg bundle')->assertDontSee('Plain latte')
            ->assertSee('show all deals');
    });

    it('says how many deals close within the hour', function () {
        dealAt(0.4, ['pickup_end' => now()->addMinutes(18)]);
        dealAt(0.4, ['pickup_end' => now()->addMinutes(60)]);
        dealAt(0.4, ['pickup_end' => now()->addMinutes(61)]);

        $this->get('/')->assertSee('2 deals close within the hour');
    });

    it('uses the singular for one deal, and a calm line when nothing is closing', function () {
        $deal = dealAt(0.4, ['pickup_end' => now()->addMinutes(18)]);
        $this->get('/')->assertSee('1 deal closes within the hour');

        $deal->update(['pickup_end' => now()->addHours(3)]);
        $this->get('/')->assertSee('Quiet right now');
    });
});

describe('empty state (mockup c05)', function () {
    it('shows "All gone nearby" and offers a wider search', function () {
        dealAt(3.5, ['title' => 'Across campus']);

        $this->get('/')
            ->assertOk()
            ->assertSee('All gone nearby.')
            ->assertSee('Search within 5 km')
            ->assertSee(e(route('deals.index', ['within' => 5])), false)
            ->assertDontSee('Across campus');
    });

    it('offers to undo each filter that is on', function () {
        $this->get('/?within=any&category=groceries&halal=1')
            ->assertSee('All gone nearby.')
            ->assertSee('Show all food')
            ->assertSee('Include food not marked halal')
            ->assertDontSee('Search within');

        $this->get('/?within=5')->assertSee('Search any distance');
    });
});

describe('error state (mockup c06)', function () {
    beforeEach(function () {
        $this->mock(DealsQuery::class)
            ->shouldReceive('inRange')
            ->andThrow(new QueryException('mysql', 'select * from listings', [], new Exception('Connection refused')));
    });

    it('shows "Couldn’t load deals" with Try again instead of a crash page', function () {
        $this->get('/?sort=cheapest')
            ->assertStatus(503)
            ->assertSee('Couldn’t load deals.')
            ->assertSee('Try again')
            ->assertSee('sort=cheapest', false)
            ->assertDontSee('Connection refused')
            ->assertDontSee('Surprise Bundles');
    });

    it('points a signed-in customer to their pickup code', function () {
        $this->get('/')->assertDontSee('Show my pickup code');

        $this->actingAs(User::factory()->create())->get('/')->assertSee('Show my pickup code');
    });
});
