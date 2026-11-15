<?php

// The bell and the inbox page (every role), "Notify me next time" on a
// sold-out deal (mockup c07) and "Turn on deal alerts" (mockup c05).

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\User;
use App\Services\Notifier;
use App\Services\SellerListingService;

beforeEach(function () {
    $this->travelTo('2026-11-13 17:00:00');
    $this->customer = User::factory()->create(['name' => 'Aina Rahman']);
    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
});

/** A draft at Roti Lane, ready to publish. */
function draftAtRotiLane(): Listing
{
    return Listing::factory()->for(test()->shop)->draft()->window(120, 240)->create([
        'title' => 'Bakery Surprise Bundle', 'original_price_sen' => 2000, 'price_sen' => 800,
    ]);
}

// ---- The inbox ----

it('sends guests to log in', function () {
    $this->get('/notifications')->assertRedirect(route('login'));
});

it('shows the bell with the number of new messages, and none for guests', function () {
    $this->get('/')->assertDontSee('Notifications');

    $this->actingAs($this->customer)->get('/')
        ->assertSee('href="'.route('notifications.index').'"', false)
        ->assertSee('Notifications')->assertDontSee('new</span>', false);

    app(Notifier::class)->limitLifted($this->customer);
    app(Notifier::class)->limitLifted($this->customer);

    $this->get('/')->assertSee('Notifications, 2 new');
});

it('lists a customer’s messages, marks the new ones, and clears the bell', function () {
    app(Notifier::class)->limitLifted($this->customer);
    app(Notifier::class)->limitLifted(User::factory()->create(['name' => 'Someone Else']));

    $this->actingAs($this->customer)->get('/notifications')->assertOk()
        ->assertSeeInOrder([
            '<h1', 'Notifications', 'FoodLoop doesn’t send email, push or SMS.',
            'href="/"', 'New: ', 'You can reserve again', 'An admin cleared your strikes and lifted your limit.',
            'Deal alerts', 'Shops you follow',
        ], false)
        // The bell on this very page is already back to zero.
        ->assertDontSee('1 new');

    expect($this->customer->unreadNotifications()->count())->toBe(0)
        ->and($this->customer->notifications()->count())->toBe(1);

    // Second visit: still listed, no longer new.
    $this->get('/notifications')->assertSee('You can reserve again')->assertDontSee('New: ');
});

it('says so when there is nothing yet', function () {
    $this->actingAs($this->customer)->get('/notifications')
        ->assertSeeInOrder(['Nothing yet', 'Your bookings, pickups and deal alerts show up here.']);
});

it('shows sellers and admins their inbox inside their own area', function () {
    app(Notifier::class)->shopApproved($this->shop);

    $this->actingAs($this->shop->user)->get('/notifications')->assertOk()
        ->assertSeeInOrder(['Seller', 'Roti Lane Bakery', 'Dashboard', 'Notifications', 'Your shop is approved', 'You can put your listings live now.'])
        ->assertDontSee('Deal alerts');

    $this->actingAs($this->shop->user)->get(route('seller.dashboard'))->assertSee('href="'.route('notifications.index').'"', false);

    $admin = User::factory()->admin()->create();
    app(Notifier::class)->shopWaiting(Seller::factory()->pending()->create(['business_name' => 'Kedai Kopi Sinar']));

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Notifications, 1 new');
    $this->get('/notifications')->assertOk()
        ->assertSeeInOrder(['Admin', 'Overview', 'Notifications', 'href="/admin/sellers"', 'New shop to approve: Kedai Kopi Sinar'], false);
});

it('shows 20 messages a page, newest first', function () {
    foreach (range(1, 21) as $minute) {
        $this->travelTo("2026-11-13 17:{$minute}:00");
        app(Notifier::class)->shopApproved(Seller::factory()->pending()->for($this->customer)->make(['business_name' => "Shop {$minute}"]));
    }

    $this->actingAs($this->customer)->get('/notifications')
        ->assertSeeInOrder(['Shop 21 is verified', 'Shop 2 is verified', 'Older'])
        ->assertDontSee('Shop 1 is verified');

    $this->get('/notifications?page=2')->assertSeeInOrder(['Shop 1 is verified', 'Newer'])->assertDontSee('Shop 2 is verified');
});

// ---- "Notify me next time" ----

it('offers "Notify me next time" on a sold-out deal, to customers and guests only', function () {
    $soldOut = Listing::factory()->for($this->shop)->soldOut()->create();
    $page = route('listings.show', $soldOut);

    $this->get($page)->assertSeeInOrder(['Sold out', 'href="'.route('login').'"', 'Notify me next time', 'Still available nearby'], false);

    $this->actingAs($this->shop->user)->get($page)->assertDontSee('Notify me next time');

    $this->actingAs($this->customer)->get($page)
        ->assertSeeInOrder(['action="'.route('shops.follow', $this->shop).'"', 'Notify me next time'], false);

    // Not on a deal that can still be reserved.
    $this->get(route('listings.show', Listing::factory()->for($this->shop)->create()))->assertDontSee('Notify me next time');
});

it('follows the shop, says so, and can be stopped again', function () {
    $page = route('listings.show', Listing::factory()->for($this->shop)->soldOut()->create());

    $this->actingAs($this->customer)->from($page)->post(route('shops.follow', $this->shop))
        ->assertRedirect($page)
        ->assertSessionHas('status', 'Done. You’ll get a notification the next time Roti Lane Bakery posts.');
    // Tapping twice changes nothing.
    $this->post(route('shops.follow', $this->shop));

    expect($this->customer->followedShops()->pluck('business_name')->all())->toBe(['Roti Lane Bakery']);

    $this->get($page)
        ->assertSee('You’ll be notified when Roti Lane Bakery posts again.')
        ->assertSee('action="'.route('shops.unfollow', $this->shop).'"', false)
        ->assertDontSee('Notify me next time');
    $this->get('/notifications')->assertSeeInOrder(['Shops you follow', 'Roti Lane Bakery', 'Stop']);

    $this->from('/notifications')->delete(route('shops.unfollow', $this->shop))
        ->assertRedirect('/notifications')
        ->assertSessionHas('status', 'You won’t be notified about Roti Lane Bakery any more.');

    expect($this->customer->followedShops()->count())->toBe(0);
});

it('only lets customers follow, and only shops the public can see', function () {
    $this->post(route('shops.follow', $this->shop))->assertRedirect(route('login'));
    $this->actingAs($this->shop->user)->post(route('shops.follow', $this->shop))->assertForbidden();
    $this->actingAs($this->customer)->post(route('shops.follow', Seller::factory()->pending()->create()))->assertNotFound();
});

it('tells followers when the shop puts a new listing live', function () {
    $this->customer->followedShops()->attach($this->shop);
    $stranger = User::factory()->create();
    $draft = draftAtRotiLane();

    app(SellerListingService::class)->publish($draft);

    expect(messageFor($this->customer, 'listing.followed_shop'))->toBe([
        'kind' => 'listing.followed_shop',
        'title' => 'Roti Lane Bakery just posted: Bakery Surprise Bundle',
        'body' => 'RM8 instead of RM20, 5 portions left. Pickup Today 7–9 PM.',
        'url' => "/listings/{$draft->id}",
    ])->and(messageKinds($stranger))->toBe([]);
});

it('tells them once per listing: not again when a paused listing comes back', function () {
    $this->customer->followedShops()->attach($this->shop);
    $this->customer->forceFill(['deal_alerts' => true])->save();
    $listings = app(SellerListingService::class);
    $listing = $listings->publish(draftAtRotiLane());

    $listings->publish($listings->pause($listing));

    // And never both messages for one listing.
    expect($listing->fresh()->status)->toBe(ListingStatus::Live)
        ->and(messageKinds($this->customer))->toBe(['listing.followed_shop']);
});

it('also tells them when the listing goes live straight from the form', function () {
    $this->customer->followedShops()->attach($this->shop);

    $this->actingAs($this->shop->user)->post(route('seller.listings.store'), [
        'type' => 'regular', 'title' => 'Croissants', 'category' => 'bakery',
        'original_price' => '12', 'price' => '5', 'quantity' => 4,
        'pickup_date' => '2026-11-13', 'pickup_start' => '19:00', 'pickup_end' => '21:00',
        'halal_status' => 'muslim_owned', 'action' => 'publish',
    ])->assertSessionHasNoErrors();

    expect(Listing::query()->where('title', 'Croissants')->value('status'))->toBe(ListingStatus::Live)
        ->and(messageFor($this->customer, 'listing.followed_shop')['title'])->toBe('Roti Lane Bakery just posted: Croissants');
});

// ---- Deal alerts ----

it('offers "Turn on deal alerts" when there are no deals', function () {
    $this->get('/')->assertSeeInOrder(['All gone nearby.', 'get a notification when the next deal drops.', 'href="'.route('login').'"', 'Turn on deal alerts'], false);

    $this->actingAs($this->shop->user)->get('/')->assertDontSee('Turn on deal alerts');

    $this->actingAs($this->customer)->get('/')
        ->assertSeeInOrder(['action="'.route('deal-alerts.update').'"', 'name="on" value="1"', 'Turn on deal alerts'], false);
});

it('turns deal alerts on and off', function () {
    $this->actingAs($this->customer)->from('/')->put(route('deal-alerts.update'), ['on' => 1])
        ->assertRedirect('/')
        ->assertSessionHas('status', 'Deal alerts are on. New deals will show up under the bell.');

    expect($this->customer->fresh()->deal_alerts)->toBeTrue();

    $this->get('/')->assertSeeInOrder(['your deal alerts are on.', 'name="on" value="0"', 'Turn off deal alerts'], false);
    $this->get('/notifications')->assertSeeInOrder(['Deal alerts', 'On. You get a notification for every new deal.', 'Turn off deal alerts']);

    $this->from('/notifications')->put(route('deal-alerts.update'), ['on' => 0])
        ->assertRedirect('/notifications')->assertSessionHas('status', 'Deal alerts are off.');

    expect($this->customer->fresh()->deal_alerts)->toBeFalse();
});

it('keeps deal alerts for customers, and can’t be set from the sign-up form', function () {
    $this->put(route('deal-alerts.update'), ['on' => 1])->assertRedirect(route('login'));
    $this->actingAs($this->shop->user)->put(route('deal-alerts.update'), ['on' => 1])->assertForbidden();
    $this->actingAs($this->customer)->put(route('deal-alerts.update'), [])->assertSessionHasErrors('on');

    expect((new User)->fill(['deal_alerts' => true])->deal_alerts)->toBeFalse();
});

it('sends a deal alert for every new deal to customers who asked, except limited ones and the shop itself', function () {
    $wantsAlerts = User::factory()->create()->forceFill(['deal_alerts' => true]);
    $wantsAlerts->save();
    $limited = User::factory()->blocked()->create()->forceFill(['deal_alerts' => true]);
    $limited->save();
    $this->shop->user->forceFill(['deal_alerts' => true])->save();
    $draft = draftAtRotiLane();

    app(SellerListingService::class)->publish($draft);

    expect(messageFor($wantsAlerts, 'listing.deal_alert'))->toBe([
        'kind' => 'listing.deal_alert',
        'title' => 'New deal: Bakery Surprise Bundle',
        'body' => 'Roti Lane Bakery · RM8 instead of RM20, 5 portions left. Pickup Today 7–9 PM.',
        'url' => "/listings/{$draft->id}",
    ])
        ->and(messageKinds($limited))->toBe([])
        ->and(messageKinds($this->shop->user))->toBe([])
        ->and(messageKinds($this->customer))->toBe([]);
});
