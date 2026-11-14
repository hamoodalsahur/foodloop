<?php

// The seller's create / edit listing form (mockup c09) and what a seller can
// do with a listing: draft, publish, pause, delete, and the lock once someone reserved.

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-11-16 17:00:00');
    Storage::fake('public');

    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $this->seller = $this->shop->user;

    $this->form = fn (array $overrides = []) => [
        'action' => 'draft',
        'type' => 'bundle',
        'title' => 'Bakery Surprise Bundle',
        'description' => '4–6 of today’s unsold breads, buns and pastries.',
        'category' => 'bakery',
        'original_price' => '20.00',
        'price' => '8',
        'quantity' => 5,
        'pickup_date' => '2026-11-16',
        'pickup_start' => '19:00',
        'pickup_end' => '21:00',
        'halal_status' => 'muslim_owned',
        'allergens' => ['egg', 'milk', 'wheat'],
        'offer_leftovers' => '1',
        ...$overrides,
    ];
});

it('shows an empty form with sensible starting values', function () {
    $this->actingAs($this->seller)->get(route('seller.listings.create'))
        ->assertOk()
        ->assertSeeInOrder(['New listing', 'Type', 'Surprise Bundle', 'Name', 'Category', 'Photo', 'Normal price (RM)', 'FoodLoop price (RM)', 'Portions', 'Pickup day', 'Halal status', 'May contain', 'Offer leftovers to a food bank', 'Save draft', 'Publish listing', 'Customer preview'])
        ->assertSee('value="2026-11-16"', false)
        ->assertSee('needs an approved JAKIM certificate');
});

it('suggests tomorrow’s lunch when tonight’s window would run past midnight', function () {
    $this->travelTo('2026-11-16 21:20:00');

    $this->actingAs($this->seller)->get(route('seller.listings.create'))
        ->assertSee('value="2026-11-17"', false)
        ->assertSee('value="12:00"', false)
        ->assertSee('value="14:00"', false);
});

it('saves a draft that students can not see yet', function () {
    $this->actingAs($this->seller)->post(route('seller.listings.store'), ($this->form)())
        ->assertRedirect(route('seller.listings.edit', Listing::sole()))
        ->assertSessionHas('status', 'Saved as a draft. Publish it when you’re ready.');

    $listing = Listing::sole();
    expect($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->seller_id)->toBe($this->shop->id)
        ->and($listing->price_sen)->toBe(800)
        ->and($listing->original_price_sen)->toBe(2000)
        ->and($listing->quantity_total)->toBe(5)
        ->and($listing->quantity_available)->toBe(5)
        ->and($listing->pickup_start->format('Y-m-d H:i'))->toBe('2026-11-16 19:00')
        ->and($listing->pickup_end->format('Y-m-d H:i'))->toBe('2026-11-16 21:00')
        ->and($listing->allergens)->toBe(['egg', 'milk', 'wheat'])
        ->and($listing->offer_leftovers)->toBeTrue();

    auth()->logout();
    $this->get('/')->assertDontSee('Bakery Surprise Bundle');
    $this->get(route('listings.show', $listing))->assertNotFound();
});

it('publishes straight away, and the deal shows up for students', function () {
    $this->actingAs($this->seller)->post(route('seller.listings.store'), ($this->form)(['action' => 'publish']))
        ->assertSessionHas('status', 'Published. Students can reserve it now.');

    expect(Listing::sole())->status->toBe(ListingStatus::Live)->published_at->not->toBeNull();

    auth()->logout();
    $this->get('/')->assertSee('Bakery Surprise Bundle');
});

it('stores an uploaded photo on the public disk', function () {
    $this->actingAs($this->seller)->post(route('seller.listings.store'), ($this->form)([
        'photo' => UploadedFile::fake()->image('today.jpg', 800, 600),
    ]));

    $path = Listing::sole()->photo_path;
    expect($path)->toStartWith('listings/');
    Storage::disk('public')->assertExists($path);
});

it('refuses form mistakes with a clear message', function (array $overrides, string $field, string $message) {
    $this->actingAs($this->seller)->post(route('seller.listings.store'), ($this->form)($overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(Listing::count())->toBe(0);
})->with([
    'no name' => [['title' => ''], 'title', 'Give the listing a name.'],
    'price not lower' => [['price' => '20'], 'price', 'The FoodLoop price has to be lower than the normal price.'],
    'price with 3 decimals' => [['price' => '8.555'], 'price', 'Use a price like 8 or 8.50.'],
    'too many portions' => [['quantity' => 51], 'quantity', 'One listing can offer up to 50 portions.'],
    'ends before it starts' => [['pickup_end' => '18:00'], 'pickup_end', 'Pickup has to end after it starts.'],
    'window too long' => [['pickup_start' => '12:00', 'pickup_end' => '19:00'], 'pickup_end', 'A pickup window can be up to 6 hours long.'],
    'yesterday' => [['pickup_date' => '2026-11-15'], 'pickup_date', 'Pickup can’t be in the past.'],
    'publish with pickup already started' => [['action' => 'publish', 'pickup_start' => '16:30', 'pickup_end' => '18:00'], 'pickup_start', 'Reservations close when pickup starts, so pickup has to start later than now.'],
    'unknown allergen' => [['allergens' => ['glitter']], 'allergens.0', 'The selected allergens.0 is invalid.'],
    'photo that is not a picture' => [['photo' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')], 'photo', 'The photo has to be a JPG, PNG or WebP picture.'],
]);

it('only allows “halal certified” with an approved certificate on file', function () {
    $this->actingAs($this->seller)->post(route('seller.listings.store'), ($this->form)(['halal_status' => 'certified']))
        ->assertSessionHasErrors(['halal_status' => '“Halal certified” needs an approved JAKIM certificate on your shop profile.']);

    $certified = Seller::factory()->approved()->certified()->create();
    $this->actingAs($certified->user)->post(route('seller.listings.store'), ($this->form)(['halal_status' => 'certified']))
        ->assertSessionHasNoErrors();

    expect(Listing::sole()->halal_status->value)->toBe('certified');
});

it('lets a shop waiting for approval write drafts but not publish', function () {
    $pending = Seller::factory()->pending()->create();

    $this->actingAs($pending->user)->get(route('seller.listings.create'))
        ->assertSee('Save draft')
        ->assertDontSee('Publish listing')
        ->assertSee('Publishing unlocks once an admin approves your shop.');

    $this->actingAs($pending->user)->post(route('seller.listings.store'), ($this->form)(['action' => 'publish']))
        ->assertSessionHasErrors(['listing' => 'Your shop has to be approved by an admin before listings can go live.']);
    expect(Listing::count())->toBe(0);

    $this->actingAs($pending->user)->post(route('seller.listings.store'), ($this->form)())->assertSessionHasNoErrors();
    expect(Listing::sole()->status)->toBe(ListingStatus::Draft);
});

describe('editing', function () {
    beforeEach(function () {
        $this->listing = Listing::factory()->for($this->shop)->create([
            'title' => 'Bakery Surprise Bundle', 'price_sen' => 800, 'original_price_sen' => 2000,
            'quantity_total' => 5, 'quantity_available' => 5,
            'pickup_start' => '2026-11-16 19:00:00', 'pickup_end' => '2026-11-16 21:00:00',
        ]);
    });

    it('changes everything while nobody has reserved', function () {
        $this->actingAs($this->seller)->put(route('seller.listings.update', $this->listing), ($this->form)([
            'price' => '6.50', 'quantity' => 8, 'pickup_start' => '19:30', 'pickup_end' => '20:30',
        ]))->assertSessionHas('status', 'Changes saved.');

        expect($this->listing->fresh())
            ->price_sen->toBe(650)
            ->quantity_total->toBe(8)
            ->quantity_available->toBe(8)
            ->status->toBe(ListingStatus::Live)
            ->and($this->listing->fresh()->pickup_start->format('H:i'))->toBe('19:30');
    });

    it('locks price, quantity, pickup time and halal status once someone has reserved', function () {
        reservations()->reserve(User::factory()->create(), $this->listing, 2, PaymentMethod::PayNow);

        $this->actingAs($this->seller)->get(route('seller.listings.edit', $this->listing))
            ->assertSee('2 portions reserved')
            ->assertSee('the price, quantity, pickup time and halal status are locked');

        $this->actingAs($this->seller)->put(route('seller.listings.update', $this->listing), ($this->form)([
            'title' => 'Bakery bag, bigger tonight', 'price' => '1', 'quantity' => 50,
            'pickup_start' => '20:30', 'pickup_end' => '21:30', 'halal_status' => 'not_halal',
        ]))->assertSessionHasNoErrors();

        expect($this->listing->fresh())
            ->title->toBe('Bakery bag, bigger tonight')
            ->price_sen->toBe(800)
            ->quantity_total->toBe(5)
            ->quantity_available->toBe(3)
            ->halal_status->value->toBe('muslim_owned')
            ->and($this->listing->fresh()->pickup_start->format('H:i'))->toBe('19:00');
    });

    it('unlocks again when every reservation was cancelled', function () {
        $reservation = reservations()->reserve(User::factory()->create(), $this->listing, 1, PaymentMethod::PayNow);
        reservations()->cancel($reservation);

        $this->actingAs($this->seller)->put(route('seller.listings.update', $this->listing), ($this->form)(['price' => '7']));

        expect($this->listing->fresh()->price_sen)->toBe(700);
    });

    it('pauses a live listing, takes it off the deals page, and puts it back', function () {
        $this->actingAs($this->seller)->post(route('seller.listings.pause', $this->listing))
            ->assertSessionHas('status', 'Paused. It’s off the deals page; reservations already made still stand.');
        expect($this->listing->fresh()->status)->toBe(ListingStatus::Paused);
        $this->get('/')->assertDontSee('Bakery Surprise Bundle');

        $this->actingAs($this->seller)->put(route('seller.listings.update', $this->listing), ($this->form)(['action' => 'publish']));
        expect($this->listing->fresh()->status)->toBe(ListingStatus::Live);
    });

    it('deletes a draft, but never a listing that has been live', function () {
        $draft = Listing::factory()->for($this->shop)->draft()->create();

        $this->actingAs($this->seller)->delete(route('seller.listings.destroy', $draft))
            ->assertRedirect(route('seller.listings.index'));
        expect(Listing::find($draft->id))->toBeNull();

        $this->actingAs($this->seller)->delete(route('seller.listings.destroy', $this->listing))
            ->assertSessionHasErrors(['listing' => 'Only a draft that was never published can be deleted. Pause it instead.']);
        expect($this->listing->fresh())->not->toBeNull();
    });

    it('refuses changes to a closed listing', function () {
        $closed = Listing::factory()->for($this->shop)->closed()->create(['title' => 'Old croissants']);

        $this->actingAs($this->seller)->get(route('seller.listings.edit', $closed))
            ->assertSee('This listing has closed, so it can’t be changed.')
            ->assertDontSee('Save changes');

        $this->actingAs($this->seller)->put(route('seller.listings.update', $closed), ($this->form)(['title' => 'Renamed']))
            ->assertSessionHasErrors(['listing' => 'This listing has closed, so it can’t be changed any more.']);
        expect($closed->fresh()->title)->toBe('Old croissants');
    });

    it('keeps other shops out of a listing', function () {
        $other = Seller::factory()->approved()->create()->user;

        $this->actingAs($other)->get(route('seller.listings.edit', $this->listing))->assertForbidden();
        $this->actingAs($other)->put(route('seller.listings.update', $this->listing), ($this->form)(['title' => 'Mine now']))->assertForbidden();
        $this->actingAs($other)->post(route('seller.listings.pause', $this->listing))->assertForbidden();
        $this->actingAs($other)->delete(route('seller.listings.destroy', $this->listing))->assertForbidden();

        expect($this->listing->fresh()->title)->toBe('Bakery Surprise Bundle');
    });
});

it('lists the shop’s listings by where they are in their life', function () {
    Listing::factory()->for($this->shop)->create(['title' => 'Live bundle']);
    Listing::factory()->for($this->shop)->draft()->create(['title' => 'Draft kuih']);
    Listing::factory()->for($this->shop)->paused()->create(['title' => 'Paused latte']);
    Listing::factory()->for($this->shop)->closed()->create(['title' => 'Closed croissants']);
    Listing::factory()->create(['title' => 'Someone else’s']);

    $this->actingAs($this->seller)->get(route('seller.listings.index'))
        ->assertOk()
        ->assertSeeInOrder(['On the deals page', 'Live bundle', 'Paused', 'Paused latte', 'Drafts', 'Draft kuih', 'Closed', 'Closed croissants'])
        ->assertDontSee('Someone else’s');
});

it('keeps customers out of the seller pages', function () {
    $this->actingAs(User::factory()->create())->get(route('seller.listings.create'))->assertForbidden();
});
