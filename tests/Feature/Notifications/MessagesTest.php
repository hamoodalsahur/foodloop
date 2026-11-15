<?php

// Who is told what, and the exact wording (all of it lives in App\Services\Notifier).
// Messages are in-app only: they are rows in the `notifications` table.

use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Seller;
use App\Models\User;
use App\Services\AdminService;
use App\Services\ListingCloser;
use App\Services\Notifier;
use App\Services\SellerListingService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Friday 13 Nov 2026, 5:00 PM. The listing's pickup is 7–9 PM.
    $this->travelTo('2026-11-13 17:00:00');

    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $this->seller = $this->shop->user;
    $this->listing = Listing::factory()->for($this->shop)->window(120, 240)->create([
        'title' => 'Bakery Surprise Bundle',
        'original_price_sen' => 2000,
        'price_sen' => 800,
        'quantity_total' => 3,
        'quantity_available' => 3,
    ]);
    $this->customer = User::factory()->create(['name' => 'Aina Rahman']);
    $this->admin = User::factory()->admin()->create();
});

it('tells the customer and the shop about a new reservation', function () {
    $reservation = reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayAtPickup);

    expect(messageFor($this->customer, 'reservation.booked'))->toBe([
        'kind' => 'reservation.booked',
        'title' => 'You’re booked: Bakery Surprise Bundle',
        'body' => "Pickup code {$reservation->pickup_code}. Collect at Roti Lane Bakery, Today 7–9 PM. Pay RM16 at pickup. Free to cancel until 6:30 PM.",
        'url' => "/orders/{$reservation->id}",
    ])->and(messageFor($this->seller, 'seller.reservation'))->toBe([
        'kind' => 'seller.reservation',
        'title' => 'New reservation: 2 × Bakery Surprise Bundle',
        'body' => 'Aina R. · pays RM16 at pickup · 1 portion left.',
        'url' => '/seller/pickups',
    ])->and(messageKinds($this->seller))->toBe(['seller.reservation'])
        ->and(messageKinds($this->admin))->toBe([]);
});

it('says "paid" for pay-now, and tells the shop when the last portion goes', function () {
    reservations()->reserve($this->customer, $this->listing, 3, PaymentMethod::PayNow);

    expect(messageFor($this->customer, 'reservation.booked')['body'])->toContain('Paid (simulated).')
        ->and(messageFor($this->seller, 'seller.reservation')['body'])->toBe('Aina R. · paid (simulated) · 0 portions left.')
        ->and(messageFor($this->seller, 'seller.sold_out'))->toMatchArray([
            'title' => 'Sold out: Bakery Surprise Bundle',
            'body' => 'All 3 portions are reserved. Pickup Today 7–9 PM.',
        ]);
});

it('thanks the customer at pickup with what they saved', function () {
    $reservation = reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayAtPickup);
    $this->travelTo('2026-11-13 19:10:00');

    reservations()->collect($this->shop, $reservation->pickup_code);

    expect(messageFor($this->customer, 'reservation.collected'))->toBe([
        'kind' => 'reservation.collected',
        'title' => 'Collected: Bakery Surprise Bundle',
        'body' => 'Thanks for rescuing 2 portions. You saved RM24 on this one.',
        'url' => '/impact',
    ]);
});

it('tells the shop about a free cancel, and the customer nothing more', function () {
    $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);

    reservations()->cancel($reservation);

    expect(messageFor($this->seller, 'seller.cancellation'))->toMatchArray([
        'title' => 'Reservation cancelled: 1 × Bakery Surprise Bundle',
        'body' => 'Aina R. won’t be coming. 1 portion is back on sale.',
        'url' => '/seller/listings',
    ])->and(messageKinds($this->customer))->toBe(['reservation.booked']);
});

it('tells the customer about the strike for a late cancel', function () {
    $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
    $this->travelTo('2026-11-13 18:45:00');

    reservations()->cancel($reservation);

    expect(messageFor($this->customer, 'customer.strike'))->toMatchArray([
        'title' => 'Late cancel: strike 1 of 3',
        'body' => 'You cancelled Bakery Surprise Bundle after the free-cancel time. At 3 strikes, reserving is paused for 7 days.',
        'url' => "/orders/{$reservation->id}",
    ]);
});

it('tells the customer and the shop about a no-show', function () {
    $reservation = reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow);
    $this->travelTo('2026-11-13 21:16:00');

    expect(reservations()->expireOverdue())->toBe(1)
        ->and(messageFor($this->customer, 'customer.strike'))->toMatchArray([
            'title' => 'Missed pickup: strike 1 of 3',
            'body' => 'You didn’t collect Bakery Surprise Bundle in time. At 3 strikes, reserving is paused for 7 days.',
        ])
        ->and(messageFor($this->seller, 'seller.no_show'))->toMatchArray([
            'title' => 'No-show: 2 × Bakery Surprise Bundle',
            'body' => 'Aina R. didn’t collect in time. 2 portions count as leftovers.',
            'url' => '/seller/donations',
        ]);
});

it('tells a customer who gets limited, including the reservations that were cancelled for them', function () {
    $this->customer->forceFill(['no_show_strikes' => 2])->save();
    $other = Listing::factory()->window(600, 720)->create(['title' => 'Nasi ayam set']);
    $kept = reservations()->reserve($this->customer, $other, 1, PaymentMethod::PayNow);
    $missed = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
    $this->travelTo('2026-11-13 21:16:00');

    reservations()->markNoShow($missed);

    expect(messageFor($this->customer, 'customer.blocked'))->toBe([
        'kind' => 'customer.blocked',
        'title' => 'Reserving is paused until Fri 20 Nov',
        'body' => 'That was strike 3. You can reserve again in 7 days. 1 reservation you still had was cancelled, and anything paid was refunded (simulated).',
        'url' => '/orders',
    ])
        // The limit message replaces the plain strike message.
        ->and(messageFor($this->customer, 'customer.strike'))->toBeNull()
        // The other shop hears that its portion is back on sale.
        ->and(messageFor($other->seller->user, 'seller.cancellation')['title'])->toBe('Reservation cancelled: 1 × Nasi ayam set')
        ->and($kept->fresh()->status->value)->toBe('cancelled');
});

it('tells the shop when an admin approves, rejects or suspends it', function () {
    $admin = app(AdminService::class);
    $pending = Seller::factory()->pending()->create(['business_name' => 'Kedai Kopi Sinar']);
    $turnedDown = Seller::factory()->pending()->create();

    $admin->approveSeller($pending);
    $admin->rejectSeller($turnedDown);
    $admin->suspendSeller($this->shop);

    expect(messageFor($pending->user, 'seller.approved'))->toMatchArray([
        'title' => 'Your shop is approved',
        'body' => 'Kedai Kopi Sinar is verified on FoodLoop. You can put your listings live now.',
        'url' => '/seller/listings',
    ])
        ->and(messageFor($turnedDown->user, 'seller.rejected')['title'])->toBe('Your shop wasn’t approved')
        ->and(messageFor($this->seller, 'seller.suspended'))->toMatchArray([
            'title' => 'Your shop is suspended',
            'body' => 'Your listings are off the deals page for now. Customers who already reserved can still collect.',
        ]);
});

it('tells the shop about a warning, with what was reported but not who reported it', function () {
    Report::factory()->for($this->listing)->for($this->customer, 'reporter')->create(['reason' => 'food_quality', 'details' => 'Bread was stale at pickup.']);

    app(AdminService::class)->warnSeller($this->listing, $this->admin);

    $message = messageFor($this->seller, 'seller.warning');

    expect($message)->toMatchArray([
        'title' => 'Warning about Bakery Surprise Bundle',
        'body' => 'Customers reported: Food quality: “Bread was stale at pickup.” The listing stays up. Please fix it.',
        'url' => '/seller',
    ])->and($message['body'])->not->toContain('Aina');
});

it('tells the shop and every waiting customer when a listing is taken down', function () {
    Report::factory()->for($this->listing)->create(['reason' => 'halal_info', 'details' => null]);
    $payNow = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
    $payLater = reservations()->reserve($second = User::factory()->create(), $this->listing, 1, PaymentMethod::PayAtPickup);

    app(AdminService::class)->hideListing($this->listing, $this->admin);

    expect(messageFor($this->seller, 'seller.takedown'))->toMatchArray([
        'title' => 'FoodLoop took down Bakery Surprise Bundle',
        'body' => 'Customers reported: Halal information. 2 reservations were cancelled for the customers.',
    ])
        ->and(messageFor($this->customer, 'reservation.cancelled_by_foodloop'))->toBe([
            'kind' => 'reservation.cancelled_by_foodloop',
            'title' => 'Reservation cancelled: Bakery Surprise Bundle',
            'body' => 'FoodLoop took this listing down after a report, so your pickup is off. Your payment was refunded (simulated). This doesn’t count as a strike.',
            'url' => "/orders/{$payNow->id}",
        ])
        ->and(messageFor($second, 'reservation.cancelled_by_foodloop')['body'])->toContain('You haven’t been charged.')
        // One takedown message for the shop, not a "cancelled" one per customer.
        ->and(messageFor($this->seller, 'seller.cancellation'))->toBeNull()
        ->and($payLater->fresh()->status->value)->toBe('cancelled');
});

it('tells a customer when an admin lifts their limit', function () {
    $limited = User::factory()->blocked()->create();

    app(AdminService::class)->liftLimit($limited);

    expect(messageFor($limited, 'customer.limit_lifted'))->toMatchArray([
        'title' => 'You can reserve again',
        'url' => '/',
    ]);
});

it('tells the shop when leftovers are offered, by the button or by the every-minute command', function () {
    FoodBankPartner::factory()->create(['name' => 'Kechara Soup Kitchen']);
    $closed = Listing::factory()->for($this->shop)->closed()->create(['title' => 'Curry puffs', 'quantity_total' => 4, 'quantity_available' => 4]);
    $automatic = Listing::factory()->for($this->shop)->closed()->create(['title' => 'Croissants', 'offer_leftovers' => true]);

    app(SellerListingService::class)->offerLeftovers($closed);
    expect(app(ListingCloser::class)->offerLeftovers())->toBe(1);

    $titles = $this->seller->notifications()->get()->where('data.kind', 'seller.leftovers_offered')->pluck('data.title')->sort()->values();

    expect($titles->all())->toBe(['Leftovers offered: 4 × Curry puffs', 'Leftovers offered: 5 × Croissants'])
        ->and($this->seller->notifications()->first()->data['body'])->toBe('Offered to Kechara Soup Kitchen. You’ll hear here when they collect.');
});

it('tells the shop what the food bank did with the leftovers', function (DonationStatus $outcome, string $title) {
    $donation = Donation::factory()
        ->for(Listing::factory()->for($this->shop)->closed()->create(['title' => 'Curry puffs']))
        ->for(FoodBankPartner::factory()->create(['name' => 'Kechara Soup Kitchen']), 'partner')
        ->create(['quantity' => 4]);

    app(AdminService::class)->settleDonation($donation, $outcome);

    expect(messageFor($this->seller, 'seller.donation_settled'))->toMatchArray([
        'title' => $title,
        'body' => '4 × Curry puffs · Kechara Soup Kitchen.',
        'url' => '/seller/donations',
    ]);
})->with([
    'collected' => [DonationStatus::Collected, 'Food bank collected your leftovers'],
    'declined' => [DonationStatus::Declined, 'Food bank couldn’t take your leftovers'],
]);

it('tells every admin about a new shop and a new report', function () {
    $secondAdmin = User::factory()->admin()->create();

    $this->post(route('seller.sign-up.store'), [
        'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => 'Sup3r-secret-pw', 'password_confirmation' => 'Sup3r-secret-pw',
        'business_name' => 'Dapur Bonda', 'ssm_no' => '202601012345', 'phone' => '012-345 6789', 'address' => 'Jalan Ilmu 2',
        'halal_claim' => 'muslim_owned',
    ])->assertRedirect(route('seller.dashboard'));
    auth()->logout();

    $this->actingAs($this->customer)->post(route('reports.store', $this->listing), ['reason' => 'food_quality', 'details' => 'Bread was stale at pickup.']);

    foreach ([$this->admin, $secondAdmin] as $admin) {
        expect(messageFor($admin, 'admin.shop_waiting'))->toMatchArray([
            'title' => 'New shop to approve: Dapur Bonda',
            'body' => 'SSM 202601012345 · Muslim-owned, not certified.',
            'url' => '/admin/sellers',
        ])->and(messageFor($admin, 'admin.report'))->toMatchArray([
            'title' => 'New report: Bakery Surprise Bundle',
            'body' => 'Roti Lane Bakery · Food quality: “Bread was stale at pickup.”',
            'url' => '/admin/reports',
        ]);
    }

    // Nobody else hears about it.
    expect(messageKinds($this->seller))->toBe([])->and(messageKinds($this->customer))->toBe([]);
});

it('never lets a message that can’t be written break the action it is about', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('The notifications table is gone.'));

    $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);

    expect($reservation->exists)->toBeTrue()
        ->and($this->listing->fresh()->quantity_available)->toBe(2);
});

it('writes links as paths, so messages from the every-minute commands open on any address', function () {
    config(['app.url' => 'http://localhost']);

    app(Notifier::class)->limitLifted($this->customer);
    app(Notifier::class)->shopApproved($this->shop);

    expect(messageFor($this->customer, 'customer.limit_lifted')['url'])->toBe('/')
        ->and(messageFor($this->seller, 'seller.approved')['url'])->toBe('/seller/listings');
});
