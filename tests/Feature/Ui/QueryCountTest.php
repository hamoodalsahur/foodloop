<?php

// The busiest pages must not ask the database more as the data grows.
// Each page is opened once with a little data and once with a lot more of
// everything it lists; the number of queries has to stay the same (no query
// per row), and under a fixed ceiling.

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Queries\AdminOverview;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');

    $this->customer = User::factory()->create(['name' => 'Aina Rahman']);
    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $this->admin = User::factory()->admin()->create();
    $this->partner = FoodBankPartner::query()->first() ?? FoodBankPartner::create(['name' => 'Test Food Bank', 'contact' => 'demo']);

    // Makes $times more of everything the pages list.
    $this->grow = function (int $times): void {
        foreach (range(1, $times) as $i) {
            // A deal at our shop and one at another shop, each with a waiting order of our customer.
            $mine = Listing::factory()->for($this->shop)->window(60, 180)->create();
            $other = Listing::factory()->for(Seller::factory()->approved())->window(60, 180)->create();
            Reservation::factory()->for($mine)->for($this->customer, 'customer')->create();
            Reservation::factory()->for($other)->for($this->customer, 'customer')->paidNow()->create();

            // History at our shop: a pickup, a cancel, and a closed listing with a no-show (a leftover).
            $closed = Listing::factory()->for($this->shop)->closed()->create([
                'pickup_start' => now()->subHours(5), 'pickup_end' => now()->subHours(3), 'closed_at' => now()->subHours(3),
            ]);
            Reservation::factory()->for($closed)->for($this->customer, 'customer')->collected()->create(['collected_at' => now()->subHours(4)]);
            Reservation::factory()->for($closed)->cancelled()->create();
            Reservation::factory()->for($closed)->noShow()->create();

            // A leftover offer that was made earlier.
            $donated = Listing::factory()->for($this->shop)->closed()->create([
                'pickup_start' => now()->subDays(2), 'pickup_end' => now()->subDays(2)->addHours(2), 'closed_at' => now()->subDays(2)->addHours(2),
            ]);
            $donation = new Donation(['quantity' => 2, 'status' => DonationStatus::Offered]);
            $donation->listing()->associate($donated);
            $donation->partner()->associate($this->partner);
            $donation->save();

            // Things waiting for the admin.
            Seller::factory()->pending()->create();
            Report::factory()->for($mine)->create();
            User::factory()->create()->forceFill(['no_show_strikes' => 1])->save();
        }
    };

    $this->queriesFor = function (User $user, string $url): int {
        // A fresh copy each time: a relation loaded on the first visit (the user's shop)
        // would otherwise be reused on the second and hide one query.
        $this->actingAs($user->fresh());
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->get($url)->assertOk();

        DB::getEventDispatcher()->forget(QueryExecuted::class);

        return $count;
    };
});

it('asks the same number of questions however much there is to show', function (string $who, string $url, int $ceiling) {
    ($this->grow)(2);

    $user = match ($who) {
        'customer' => $this->customer,
        'seller' => $this->shop->user,
        'admin' => $this->admin,
    };
    $url = str_replace(
        ['{listing}', '{order}'],
        [(string) Listing::open()->value('id'), (string) Reservation::query()->value('id')],
        $url,
    );

    $few = ($this->queriesFor)($user, $url);

    ($this->grow)(8);

    $many = ($this->queriesFor)($user, $url);

    expect($many)->toBe($few, "{$url} ran {$few} queries with a little data and {$many} with more: a query per row crept in")
        ->and($many)->toBeLessThanOrEqual($ceiling, "{$url} ran {$many} queries; the ceiling is {$ceiling}");
})->with([
    'deals' => ['customer', '/', 4],
    'map' => ['customer', '/map', 4],
    'listing' => ['customer', '/listings/{listing}', 5],
    'orders' => ['customer', '/orders', 5],
    'impact' => ['customer', '/impact', 7],
    'seller dashboard' => ['seller', '/seller', 12],
    'seller pickups' => ['seller', '/seller/pickups', 6],
    'seller donations' => ['seller', '/seller/donations', 8],
    'admin overview' => ['admin', '/admin', 16],
    'admin orders' => ['admin', '/admin/orders?tab=all', 9],
    'admin order' => ['admin', '/admin/orders/{order}', 6],
    'admin food banks' => ['admin', '/admin/food-banks', 8],
]);

it('adds up the platform totals in the database exactly as before', function () {
    $listing = Listing::factory()->create(['original_price_sen' => 2000, 'price_sen' => 800]);
    // Priced above its "normal" price: saves nothing, never a negative saving.
    $odd = Listing::factory()->create(['original_price_sen' => 500, 'price_sen' => 700]);

    Reservation::factory()->for($listing)->collected()->create(['quantity' => 2]);
    Reservation::factory()->for($listing)->collected()->create(['quantity' => 1]);
    Reservation::factory()->for($odd)->collected()->create(['quantity' => 3]);
    Reservation::factory()->for($listing)->create(['quantity' => 3]);
    Reservation::factory()->for($listing)->cancelled()->create(['quantity' => 3]);
    Reservation::factory()->for($listing)->noShow()->create(['quantity' => 3]);

    expect((new AdminOverview)->totals())
        ->mealsRescued->toBe(6)
        ->savedSen->toBe(3600);
});

it('gives zeros, not an error, when nothing has been collected yet', function () {
    expect((new AdminOverview)->totals())->mealsRescued->toBe(0)->savedSen->toBe(0);

    $this->actingAs($this->shop->user)->get('/seller')->assertOk();
    $this->actingAs($this->admin)->get('/admin/food-banks')->assertOk();
});
