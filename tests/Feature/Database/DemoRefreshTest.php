<?php

// `php artisan demo:refresh`: fresh deals, same accounts and history
// (App\Support\Demo\DemoRefresh). `migrate:fresh --seed` stays the full wipe.

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Queries\CustomerImpact;
use App\Queries\SellerOverview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    $this->user = fn (string $login) => User::where('email', "{$login}@foodloop.test")->sole();
    $this->shop = fn (string $login) => ($this->user)($login)->seller;
    $this->live = fn (string $title) => Listing::live()->where('title', $title)->sole();
});

it('keeps every account, also one that signed up after seeding', function () {
    $this->post('/register', [
        'name' => 'Nur Izzati', 'email' => 'izzati@student.example',
        'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
    ]);
    auth()->logout();

    $before = User::orderBy('id')->get(['id', 'email', 'role', 'password', 'no_show_strikes', 'blocked_until'])->toArray();

    $this->artisan('demo:refresh')->assertSuccessful();

    expect(User::orderBy('id')->get(['id', 'email', 'role', 'password', 'no_show_strikes', 'blocked_until'])->toArray())->toBe($before)
        ->and(User::where('email', 'izzati@student.example')->exists())->toBeTrue()
        // The three shops waiting for approval are still waiting.
        ->and(Seller::where('status', SellerStatus::Pending)->count())->toBe(3)
        ->and(Seller::count())->toBe(8);
});

it('keeps collected, cancelled and no-show orders exactly as they were', function () {
    $aina = ($this->user)('aina');
    $priya = ($this->user)('priya');

    // A rehearsal happened: Aina's sandwich pack was handed over, Priya reserved a latte and cancelled it.
    $collected = reservations()->collect(($this->shop)('sebunga'), '2468');
    $cancelled = reservations()->cancel(reservations()->reserve($priya, ($this->live)('Iced latte'), 1, PaymentMethod::PayAtPickup));

    $history = fn () => Reservation::where('status', '!=', ReservationStatus::Reserved)->orderBy('id')->get()->toArray();
    $before = $history();

    expect($collected->status)->toBe(ReservationStatus::Collected)
        ->and($cancelled->status)->toBe(ReservationStatus::Cancelled)
        ->and(Reservation::where('status', ReservationStatus::NoShow)->count())->toBe(1);

    $this->artisan('demo:refresh')->assertSuccessful();

    expect($history())->toBe($before)
        // Aina's impact: the 14 seeded meals plus the sandwich pack she collected.
        ->and((new CustomerImpact($aina))->mealsRescued())->toBe(15)
        ->and(Donation::count())->toBe(3)
        ->and(Report::count())->toBe(1);
});

it('removes only the reservations that were still waiting, with their messages, and gives nobody a strike', function () {
    $waiting = Reservation::where('status', ReservationStatus::Reserved)->pluck('id');
    $others = Reservation::where('status', '!=', ReservationStatus::Reserved)->pluck('id');
    $strikes = User::orderBy('id')->pluck('no_show_strikes', 'email')->all();
    $oldListings = Listing::pluck('id');

    expect($waiting)->toHaveCount(7);

    $this->artisan('demo:refresh')->assertSuccessful();

    expect(Reservation::whereKey($waiting)->count())->toBe(0)
        ->and(Reservation::whereKey($others)->count())->toBe($others->count())
        ->and(Reservation::whereIn('listing_id', $oldListings)->where('status', ReservationStatus::Reserved)->count())->toBe(0)
        ->and(User::orderBy('id')->pluck('no_show_strikes', 'email')->all())->toBe($strikes);

    // Later the clockwork runs: nothing of the old deals can turn into a no-show.
    $this->travel(config('foodloop.grace_minutes') + 1)->minutes();
    $this->artisan('reservations:expire');

    expect(Reservation::whereIn('listing_id', $oldListings)->where('status', ReservationStatus::NoShow)->count())->toBe(1) // Daniel's, seeded
        ->and(User::orderBy('id')->pluck('no_show_strikes', 'email')->all())->toBe($strikes);

    // Every "You're booked" message opens an order that exists, one per waiting reservation.
    $aina = ($this->user)('aina');
    $booked = $aina->notifications()->get()->where('data.kind', 'reservation.booked');

    expect($booked->count())->toBe(2)
        ->and($booked->every(fn ($message) => Reservation::whereKey(str($message->data['url'])->afterLast('/')->toString())->exists()))->toBeTrue()
        ->and($booked->pluck('data.body')->implode(' '))->toContain('Pickup code 2468')->toContain('Pickup code 4721');

    // The shops' "New reservation" messages match what is waiting now, too.
    foreach (['bondamawar' => 2, 'sebunga' => 1, 'kopisaigon' => 2] as $login => $count) {
        expect(($this->user)($login)->notifications()->get()->where('data.kind', 'seller.reservation')->count())->toBe($count, $login);
    }
});

it('closes every listing that was still open without offering leftovers to the food bank', function () {
    // The owner tried the seller pages: a draft and a paused listing at Se.Bunga.
    $draft = Listing::factory()->for(($this->shop)('sebunga'))->draft()->create(['title' => 'My test draft']);
    $paused = Listing::factory()->for(($this->shop)('sebunga'))->paused()->create(['title' => 'My paused deal']);

    $open = Listing::whereIn('status', [ListingStatus::Draft, ListingStatus::Live, ListingStatus::SoldOut, ListingStatus::Paused])->pluck('id');
    $oldBundle = ($this->live)('Bakery Surprise Bundle');

    expect($open)->toHaveCount(7)
        ->and($oldBundle->offer_leftovers)->toBeTrue();

    $this->freezeTime();

    $this->artisan('demo:refresh')->assertSuccessful();

    Listing::whereKey($open)->get()->each(function (Listing $listing) {
        expect($listing->status)->toBe(ListingStatus::Closed, $listing->title)
            ->and($listing->closed_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    // The old bundle: both reservations were waiting, so all 5 are "left" again.
    expect($oldBundle->refresh()->quantity_available)->toBe(5);

    // The every-minute command finds nothing to offer, now or after the windows have ended.
    $this->artisan('listings:close');
    $this->travel(4)->hours();
    $this->artisan('reservations:expire');
    $this->artisan('listings:close');

    expect(Donation::whereIn('listing_id', $open)->count())->toBe(0)
        ->and(Donation::whereHas('listing', fn ($q) => $q->where('title', 'Bakery Surprise Bundle')->whereKeyNot($oldBundle->id)->where('pickup_end', '>', now()->subDay()))->count())
        ->toBe(1); // the NEW bundle's leftovers were offered, as they should be
});

it('does not list the deals it took off sale as leftovers on the seller dashboard', function () {
    $shops = ['sebunga', 'bondamawar', 'kuihtupah', 'kopisaigon', 'merbokroasted'];
    $leftovers = fn () => collect($shops)->mapWithKeys(
        fn (string $login) => [$login => (new SellerOverview(($this->shop)($login)))->leftoversToOffer()->modelKeys()]
    )->all();

    $before = $leftovers();

    $this->artisan('demo:refresh')->assertSuccessful();
    $this->artisan('demo:refresh --menu=random')->assertSuccessful();

    expect($leftovers())->toBe($before);
});

it('keeps stock and reservations adding up on every listing', function () {
    reservations()->collect(($this->shop)('sebunga'), '2468');
    reservations()->reserve(($this->user)('priya'), ($this->live)('Iced latte'), 2, PaymentMethod::PayNow);

    $this->artisan('demo:refresh')->assertSuccessful();
    $this->artisan('demo:refresh')->assertSuccessful();

    Listing::with('reservations')->get()->each(function (Listing $listing) {
        $taken = $listing->reservations->where('status', '!=', ReservationStatus::Cancelled)->sum('quantity');

        expect($listing->quantity_available)->toBe($listing->quantity_total - $taken, $listing->title);
    });
});

it('posts the same fresh starting point again: the deals, Aina\'s two codes and a pickup that is open now', function () {
    $this->travel(3)->hours();   // the seeded deals are long over
    $this->artisan('demo:refresh')->assertSuccessful();

    expect(Listing::live()->pluck('title')->sort()->values()->all())->toBe([
        'Bakery Surprise Bundle', 'Curry puffs × 4', 'Iced latte', 'Nasi ayam set (2 pax)', 'Sandwich & pastry pack',
    ])
        ->and(Listing::open()->count())->toBe(4);

    $bundle = ($this->live)('Bakery Surprise Bundle');

    expect($bundle->quantity_available)->toBe(3)
        ->and($bundle->price_formatted)->toBe('RM8')
        ->and($bundle->reservations()->where('status', ReservationStatus::Reserved)->pluck('pickup_code')->all())->toBe(['4721', '3390'])
        ->and(Reservation::where('pickup_code', '4721')->where('status', ReservationStatus::Reserved)->sole()->customer->email)->toBe('aina@foodloop.test');

    // Student reserves, seller hands over: the two steps of the demo script.
    $reserved = reservations()->reserve(($this->user)('aina'), $bundle, 1, PaymentMethod::PayAtPickup);
    $handedOver = reservations()->collect(($this->shop)('sebunga'), '2468');

    expect($reserved->status)->toBe(ReservationStatus::Reserved)
        ->and($handedOver->customer->email)->toBe('aina@foodloop.test')
        ->and($handedOver->listing->title)->toBe('Sandwich & pastry pack');
});

it('can run any number of times without piling up today\'s history', function () {
    foreach (range(1, 3) as $run) {
        $this->artisan('demo:refresh')->assertSuccessful();

        expect(Listing::live()->count())->toBe(5, "run {$run}")
            ->and(Reservation::where('status', ReservationStatus::Reserved)->count())->toBe(7)
            // Seeded once, not added again on the same day: the croissant deal, Mei Ling's pickup, her report.
            ->and(Reservation::where('pickup_code', '6208')->count())->toBe(1)
            ->and(Reservation::where('pickup_code', '8059')->count())->toBe(1)
            ->and(Report::count())->toBe(1)
            ->and(Donation::count())->toBe(3)
            ->and(User::count())->toBe(16);
    }

    // Without Mei Ling's portion the sandwich pack still says 3 left.
    expect(($this->live)('Sandwich & pastry pack')->only(['quantity_total', 'quantity_available']))
        ->toBe(['quantity_total' => 4, 'quantity_available' => 3]);

    // The next day "earlier today" is added again, so Bonda Mawar's dashboard is never empty.
    $this->travel(1)->days();
    $this->artisan('demo:refresh')->assertSuccessful();

    expect(Reservation::where('pickup_code', '6208')->count())->toBe(2)
        ->and(Donation::count())->toBe(4)
        ->and(Report::count())->toBe(1);
});

it('posts a random pick when asked, the same one for the same number', function () {
    $this->travelTo('2026-11-18 18:18:00');

    $tonight = fn () => Listing::whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut])->orderBy('title')->pluck('title')->all();

    $this->artisan('demo:refresh --menu=random --seed=7')->assertSuccessful();
    $first = $tonight();

    $this->artisan('demo:refresh --menu=random --seed=8')->assertSuccessful();
    $second = $tonight();

    $this->artisan('demo:refresh --menu=random --seed=7')->assertSuccessful();

    expect(count($first))->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(9)
        ->and($first)->toContain('Bakery Surprise Bundle', 'Sandwich & pastry pack')
        ->and($second)->not->toBe($first)
        ->and($tonight())->toBe($first);
});

it('tells the owner what it did', function () {
    $this->artisan('demo:refresh')
        ->expectsOutputToContain('5 fresh listings posted, 5 old ones closed, 7 waiting reservation(s) removed')
        ->expectsOutputToContain('4721')
        ->assertSuccessful();
});

it('refuses, and changes nothing, when a demo shop is no longer approved', function () {
    ($this->shop)('kopisaigon')->forceFill(['status' => SellerStatus::Suspended])->save();

    $this->artisan('demo:refresh')
        ->expectsOutputToContain('Kopi Saigon Merbok as an approved shop')
        ->assertFailed();

    expect(Listing::live()->count())->toBe(5)
        ->and(Reservation::where('status', ReservationStatus::Reserved)->count())->toBe(7);
});

it('refuses bad options and production', function () {
    $this->artisan('demo:refresh --menu=breakfast')->assertFailed();
    $this->artisan('demo:refresh --seed=abc')->assertFailed();

    app()->detectEnvironment(fn () => 'production');
    $this->artisan('demo:refresh')->expectsOutputToContain('must not run in production')->assertFailed();

    expect(Listing::live()->count())->toBe(5);
});
