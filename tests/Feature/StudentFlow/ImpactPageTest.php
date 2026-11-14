<?php

// The impact page at /impact (mockup c04): computed from collected pickups only.

use App\Models\Donation;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Queries\CustomerImpact;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // A Wednesday. This week started Monday 16 Nov.
    $this->travelTo('2026-11-18 18:18:00');
    $this->customer = User::factory()->create(['name' => 'Aina Rahman']);

    $shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);

    /** A pickup the customer collected at $when: $qty portions at RM8 instead of RM20. */
    $this->pickup = fn (string $when, int $qty = 1, ?User $by = null, string $title = 'Bakery Surprise Bundle') => Reservation::factory()
        ->for(Listing::factory()->for($shop)->closed()->create(['title' => $title, 'original_price_sen' => 2000, 'price_sen' => 800]))
        ->collected()
        ->create(['customer_id' => ($by ?? $this->customer)->id, 'quantity' => $qty, 'collected_at' => $when]);
});

it('is for customers only', function () {
    $this->get('/impact')->assertRedirect('/login');
    $this->actingAs(User::factory()->seller()->create())->get('/impact')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/impact')->assertForbidden();
    $this->actingAs($this->customer)->get('/impact')->assertOk();
});

it('is switched on in the menu and marked as the current page', function () {
    $this->actingAs($this->customer)->get('/impact')
        ->assertSee('href="'.route('impact').'"', false)
        ->assertDontSee('Impact<span class="sr-only"> (coming soon)</span>', false);
});

it('counts meals and savings from collected pickups only', function () {
    ($this->pickup)('2026-11-17 19:00:00', 2);
    ($this->pickup)('2026-11-10 19:00:00', 1);

    // None of these were rescued by this customer.
    $listing = Listing::factory()->create(['original_price_sen' => 2000, 'price_sen' => 800]);
    Reservation::factory()->for($listing)->create(['customer_id' => $this->customer->id]);
    Reservation::factory()->for($listing)->cancelled()->create(['customer_id' => $this->customer->id]);
    Reservation::factory()->for($listing)->noShow()->create(['customer_id' => $this->customer->id]);
    ($this->pickup)('2026-11-17 19:00:00', 3, User::factory()->create());

    $this->actingAs($this->customer)->get('/impact')
        ->assertSeeInOrder(['Your impact', 'Completed pickups only', 'Meals rescued', '3', 'You saved', 'RM36', 'No-shows', '0 / 3']);
});

it('shows the customer\'s strikes out of the limit', function () {
    $this->customer->forceFill(['no_show_strikes' => 2])->save();

    $this->actingAs($this->customer)->get('/impact')->assertSeeInOrder(['No-shows', '2 / 3']);
});

it('puts pickups into the last six calendar weeks, this week last', function () {
    ($this->pickup)('2026-10-12 08:00:00', 1);   // W1: 12–18 Oct
    ($this->pickup)('2026-10-25 23:59:00', 2);   // W2: 19–25 Oct (Sunday night still counts)
    ($this->pickup)('2026-10-26 00:00:00', 3);   // W3: 26 Oct–1 Nov (Monday midnight starts it)
    ($this->pickup)('2026-11-16 09:00:00', 2);   // W6: this week
    ($this->pickup)('2026-11-18 12:00:00', 1);   // W6
    ($this->pickup)('2026-10-11 20:00:00', 5);   // older than six weeks: in the totals, not in the chart

    $weeks = (new CustomerImpact($this->customer))->weeks();

    expect($weeks->pluck('meals')->all())->toBe([1, 2, 3, 0, 0, 3])
        ->and($weeks->pluck('label')->all())->toBe(['W1', 'W2', 'W3', 'W4', 'W5', 'W6'])
        ->and($weeks->pluck('dates')->all())->toBe(['12–18 Oct', '19–25 Oct', '26 Oct–1 Nov', '2–8 Nov', '9–15 Nov', '16–22 Nov'])
        ->and($weeks->pluck('current')->all())->toBe([false, false, false, false, false, true]);

    $this->actingAs($this->customer)->get('/impact')
        ->assertSeeInOrder(['Meals rescued', '14'])
        ->assertSeeInOrder(['Per week', 'Last 6 weeks', 'W1', 'W6', 'This week'])
        // The same numbers as a table for screen readers.
        ->assertSeeInOrder(['Meals per week, last 6 weeks', '12–18 Oct', '1', '26 Oct–1 Nov', '3', '16–22 Nov (this week)', '3']);
});

it('lists the latest pickups with what each one saved', function () {
    ($this->pickup)('2026-11-18 12:00:00', 1, title: 'Bakery Surprise Bundle');
    ($this->pickup)('2026-11-16 19:00:00', 2, title: 'Butter croissants × 3');
    ($this->pickup)('2026-11-02 19:00:00', 1, title: 'Old pickup');

    $this->actingAs($this->customer)->get('/impact')
        ->assertSeeInOrder([
            'Recent',
            'Bakery Surprise Bundle', 'Roti Lane Bakery · Today', '−RM12',
            'Butter croissants × 3', 'Roti Lane Bakery · Mon 16 Nov · Qty 2', '−RM24',
            'Old pickup', 'Mon 2 Nov',
        ]);
});

it('shows only the five latest pickups', function () {
    foreach (range(1, 7) as $day) {
        ($this->pickup)("2026-11-0{$day} 19:00:00", title: "Pickup number {$day}");
    }

    $this->actingAs($this->customer)->get('/impact')
        ->assertSee('Pickup number 7')->assertSee('Pickup number 3')
        ->assertDontSee('Pickup number 2');
});

it('adds up what everyone rescued and donated this month', function () {
    ($this->pickup)('2026-11-17 19:00:00', 2);
    ($this->pickup)('2026-11-01 00:00:00', 3, User::factory()->create());
    ($this->pickup)('2026-10-31 23:59:00', 9, User::factory()->create());

    Donation::factory()->create(['quantity' => 4]);
    Donation::factory()->create(['quantity' => 2, 'status' => 'collected']);
    Donation::factory()->create(['quantity' => 7, 'status' => 'declined']);
    Donation::factory()->create(['quantity' => 5, 'created_at' => '2026-10-20 10:00:00']);

    $this->actingAs($this->customer)->get('/impact')
        ->assertSeeInOrder(['Everyone on FoodLoop this month:', '5 meals', 'rescued and', '6 items', 'sent to food banks.']);
});

it('never counts a "saving" below zero', function () {
    $pricey = Listing::factory()->closed()->create(['original_price_sen' => 500, 'price_sen' => 800]);
    Reservation::factory()->for($pricey)->collected()->create(['customer_id' => $this->customer->id]);

    expect((new CustomerImpact($this->customer))->savedSen())->toBe(0);
});

it('invites a customer with no pickups yet to find a deal', function () {
    $this->actingAs($this->customer)->get('/impact')
        ->assertOk()
        ->assertSeeInOrder(['Meals rescued', '0', 'You saved', 'RM0', 'Nothing rescued yet', 'See tonight’s deals'])
        ->assertSee('0 meals')
        ->assertDontSee('Per week')
        ->assertDontSee('Recent');
});

it('matches the mockup for Aina on the seeded demo data', function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->travelBack();
    $this->seed(DatabaseSeeder::class);
    $aina = User::firstWhere('email', 'aina@foodloop.test');

    expect((new CustomerImpact($aina))->weeks()->pluck('meals')->all())->toBe([1, 2, 4, 2, 3, 2]);

    $this->actingAs($aina)->get('/impact')
        ->assertSeeInOrder(['Meals rescued', '14', 'You saved', 'RM118', 'No-shows', '0 / 3'])
        ->assertSeeInOrder(['Recent', 'Bakery Surprise Bundle', 'Roti Lane Bakery', '−RM12']);
});
