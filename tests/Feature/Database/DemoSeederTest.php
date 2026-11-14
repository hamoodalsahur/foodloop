<?php

use App\Enums\DonationStatus;
use App\Enums\HalalStatus;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);
});

it('creates the four approved shops from the mockups, one seller account each', function () {
    $shops = Seller::where('status', SellerStatus::Approved)->with('user')->get();

    expect($shops->pluck('business_name')->sort()->values()->all())->toBe([
        'Daily Grind Café', 'Kafe Seri Ilmu', 'Mak Long’s Kuih', 'Roti Lane Bakery',
    ]);

    $shops->each(fn (Seller $shop) => expect($shop->user->role)->toBe(UserRole::Seller));

    expect(Seller::firstWhere('business_name', 'Roti Lane Bakery')->hasApprovedHalalCertificate())->toBeTrue();
});

it('creates the three shops waiting for approval from the admin mockup', function () {
    expect(Seller::where('status', SellerStatus::Pending)->pluck('business_name')->sort()->values()->all())
        ->toBe(['Dapur Bonda', 'Kedai Kopi Sinar', 'Sweet Crumbs Bakehouse']);

    $sweetCrumbs = Seller::firstWhere('business_name', 'Sweet Crumbs Bakehouse');

    expect($sweetCrumbs->ssm_no)->toBeNull()
        ->and($sweetCrumbs->halal_claim)->toBe(HalalStatus::Certified)
        ->and($sweetCrumbs->halal_cert_path)->toBeNull();
});

it('creates one admin and seven customers', function () {
    expect(User::where('role', UserRole::Admin)->pluck('email')->all())->toBe(['admin@foodloop.test'])
        ->and(User::where('role', UserRole::Customer)->count())->toBe(7)
        ->and(User::where('role', UserRole::Seller)->count())->toBe(7);
});

it('gives every demo account the documented password and a foodloop.test email', function () {
    User::all()->each(function (User $user) {
        expect($user->email)->toEndWith('@foodloop.test')
            ->and(Hash::check(DemoSeeder::PASSWORD, $user->password))->toBeTrue();
    });
});

it('uses the listings and prices from the mockups', function (string $title, string $shop, string $price, string $was) {
    $listing = Listing::live()->where('title', $title)->with('seller')->sole();

    expect($listing->seller->business_name)->toBe($shop)
        ->and($listing->price_formatted)->toBe($price)
        ->and($listing->original_price_formatted)->toBe($was)
        ->and($listing->pickup_end->isFuture())->toBeTrue();
})->with([
    ['Curry puffs × 4', 'Mak Long’s Kuih', 'RM3', 'RM6'],
    ['Iced latte', 'Daily Grind Café', 'RM4.50', 'RM9'],
    ['Bakery Surprise Bundle', 'Roti Lane Bakery', 'RM8', 'RM20'],
    ['Nasi ayam set (2 pax)', 'Kafe Seri Ilmu', 'RM6.50', 'RM13'],
    ['Sandwich & pastry pack', 'Daily Grind Café', 'RM9', 'RM24'],
]);

it('matches the bakery bundle on the listing mockup', function () {
    $bundle = Listing::live()->where('title', 'Bakery Surprise Bundle')->sole();

    expect($bundle->discount_percent)->toBe(60)
        ->and($bundle->quantity_total)->toBe(5)
        ->and($bundle->quantity_available)->toBe(3)
        ->and($bundle->halal_status)->toBe(HalalStatus::Certified)
        ->and($bundle->allergens)->toBe(['egg', 'milk', 'wheat'])
        ->and((int) $bundle->pickup_start->diffInMinutes($bundle->pickup_end))->toBe(120);
});

it('matches the pickups table on the seller mockup', function () {
    // Tonight's reservations at Roti Lane Bakery, in the order they were made
    // (Aina's older, already collected orders are left out).
    $aina = User::firstWhere('email', 'aina@foodloop.test');

    $pickups = Reservation::whereHas('listing.seller', fn ($q) => $q->where('business_name', 'Roti Lane Bakery'))
        ->where(fn ($q) => $q->where('customer_id', '!=', $aina->id)->orWhere('status', ReservationStatus::Reserved))
        ->with('customer')
        ->orderBy('id')
        ->get()
        ->map(fn (Reservation $r) => [$r->pickup_code, $r->customer->name, $r->quantity, $r->status])
        ->all();

    expect($pickups)->toBe([
        ['4721', 'Aina Rahman', 1, ReservationStatus::Reserved],
        ['3390', 'Hafiz Mansor', 1, ReservationStatus::Reserved],
        ['6208', 'Priya Selvam', 1, ReservationStatus::Collected],
        ['1185', 'Mei Ling Tan', 2, ReservationStatus::Collected],
        ['9052', 'Daniel Khoo', 1, ReservationStatus::NoShow],
    ]);
});

it('gives every seeded reservation a fixed pickup code, so the demo is the same every time', function () {
    $tonight = ['4721', '3390', '6208', '1185', '9052', '2846', '5173', '7730', '6412', '8059', '2468'];
    $ainasHistory = array_map(strval(...), range(1000, 1011));

    expect(Reservation::pluck('pickup_code')->sort()->values()->all())
        ->toBe(collect([...$tonight, ...$ainasHistory])->sort()->values()->all());
});

it('keeps stock and reservations adding up on every listing', function () {
    Listing::with('reservations')->get()->each(function (Listing $listing) {
        $taken = $listing->reservations
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->sum('quantity');

        expect($listing->quantity_available)->toBe($listing->quantity_total - $taken, $listing->title);
    });
});

it('gives each reserved reservation a pickup code that is unique within its shop', function () {
    $reserved = Reservation::where('status', ReservationStatus::Reserved)->with('listing')->get();

    $reserved->groupBy('listing.seller_id')->each(
        fn ($group) => expect($group->pluck('pickup_code')->unique()->count())->toBe($group->count())
    );
});

it('gives Aina the impact numbers from the mockup', function () {
    $collected = User::firstWhere('email', 'aina@foodloop.test')
        ->reservations()
        ->where('status', ReservationStatus::Collected)
        ->with('listing')
        ->get();

    $perWeek = $collected
        ->groupBy(fn (Reservation $r) => (int) $r->collected_at->startOfWeek()->diffInWeeks(now()->startOfWeek()))
        ->map->sum('quantity')
        ->sortKeysDesc()
        ->values()
        ->all();

    expect($collected->sum('quantity'))->toBe(14)
        ->and($collected->sum(fn (Reservation $r) => $r->listing->saving_sen * $r->quantity))->toBe(11800)
        ->and($perWeek)->toBe([1, 2, 4, 2, 3, 2])
        ->and($collected->every(fn (Reservation $r) => $r->collected_at->isPast()))->toBeTrue();
});

it('sets the no-show limits from the admin mockup', function () {
    $daniel = User::firstWhere('email', 'daniel@foodloop.test');
    $farah = User::firstWhere('email', 'farah@foodloop.test');
    $jason = User::firstWhere('email', 'jason@foodloop.test');

    expect($daniel->isBlocked())->toBeTrue()
        ->and($daniel->no_show_strikes)->toBe(0)
        ->and($farah->isBlocked())->toBeTrue()
        ->and($jason->isBlocked())->toBeFalse()
        ->and($jason->no_show_strikes)->toBe(2);
});

it('seeds the placeholder food bank partner and one open report', function () {
    expect(FoodBankPartner::pluck('name')->all())->toBe(['[FOOD BANK PARTNER NAME]'])
        ->and(Report::sole()->details)->toBe('Bread was stale at pickup.')
        ->and(Report::sole()->listing->title)->toBe('Sandwich & pastry pack');
});

it('copies the demo food pictures and points every listing at one that exists', function () {
    expect(Storage::disk('public')->files('listings/demo'))->toHaveCount(7);

    Listing::all()->each(
        fn (Listing $listing) => Storage::disk('public')->assertExists($listing->photo_path)
    );
});

it('keeps real-looking details as obvious placeholders', function () {
    Seller::where('status', SellerStatus::Approved)->get()->each(function (Seller $shop) {
        expect($shop->ssm_no)->toBe('[SSM NO.]')
            ->and($shop->phone)->toBe('[PHONE]')
            ->and($shop->address)->toBe('[SHOP ADDRESS]');
    });
});

it('has a pickup that can be handed over right away, for the live demo', function () {
    $ainas = Reservation::firstWhere('pickup_code', '2468');

    expect($ainas->customer->email)->toBe('aina@foodloop.test')
        ->and($ainas->listing->seller->business_name)->toBe('Daily Grind Café')
        ->and($ainas->listing->pickup_start->isPast())->toBeTrue()
        ->and($ainas->listing->pickup_end->isFuture())->toBeTrue();

    // The seller types the code and it works, with no waiting for a pickup window.
    $collected = reservations()->collect($ainas->listing->seller, '2468');

    expect($collected->status)->toBe(ReservationStatus::Collected);
});

it('has food-bank donations, so the donation numbers are not all zero', function () {
    $donations = Donation::with('listing.seller', 'partner')->get();

    expect($donations)->toHaveCount(3)
        ->and($donations->pluck('listing.seller.business_name')->unique()->all())->toBe(['Roti Lane Bakery'])
        ->and($donations->pluck('partner.name')->unique()->all())->toBe(['[FOOD BANK PARTNER NAME]'])
        ->and($donations->where('status', DonationStatus::Collected)->sum('quantity'))->toBe(8)
        ->and($donations->where('status', DonationStatus::Offered)->sum('quantity'))->toBe(1);

    // Never more than was actually left over.
    $donations->each(fn ($donation) => expect($donation->quantity)->toBeLessThanOrEqual($donation->listing->leftovers()));
});

it('does nothing when the demo data is already there', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@foodloop.test')->count())->toBe(1)
        ->and(Listing::count())->toBe(18);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class, 'must not run in production');
});
