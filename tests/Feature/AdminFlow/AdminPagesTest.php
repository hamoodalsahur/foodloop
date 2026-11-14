<?php

// The admin area (mockup c10): overview, sellers, reports, customers.

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Aziz']);
    $this->actingAs($this->admin);
});

describe('who gets in', function () {
    it('keeps everyone but admins out of every admin page and button', function (string $method, string $url) {
        $seller = Seller::factory()->create();
        $listing = Listing::factory()->create();
        $customer = User::factory()->create();
        $url = str_replace(['{seller}', '{listing}', '{user}'], [$seller->id, $listing->id, $customer->id], $url);

        auth()->logout();
        $this->{$method}($url)->assertRedirect('/login');

        $this->actingAs($customer)->{$method}($url)->assertForbidden();
        $this->actingAs($listing->seller->user)->{$method}($url)->assertForbidden();
    })->with([
        ['get', '/admin'],
        ['get', '/admin/sellers'],
        ['get', '/admin/reports'],
        ['get', '/admin/customers'],
        ['get', '/admin/sellers/{seller}/certificate'],
        ['post', '/admin/sellers/{seller}/approve'],
        ['post', '/admin/sellers/{seller}/reject'],
        ['post', '/admin/sellers/{seller}/suspend'],
        ['post', '/admin/reports/{listing}/hide'],
        ['post', '/admin/reports/{listing}/warn'],
        ['post', '/admin/reports/{listing}/dismiss'],
        ['post', '/admin/customers/{user}/lift-limit'],
    ]);
});

describe('overview', function () {
    it('shows the four numbers that matter', function () {
        Seller::factory()->count(3)->create();
        Report::factory()->count(2)->create();
        Listing::factory()->closed()->create();
        Reservation::factory()->collected()->create(['quantity' => 3, 'collected_at' => '2026-11-10 19:00:00']);
        Reservation::factory()->collected()->create(['quantity' => 2, 'collected_at' => '2026-10-30 19:00:00']);

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Overview', 'Things that need a decision come first.'])
            ->assertSeeInOrder(['Sellers to approve', '3', 'Open reports', '2', 'Live listings', '4', 'Meals rescued this month', '3']);
    });

    it('lists shops waiting for approval, longest wait first, with what to check', function () {
        Seller::factory()->create(['business_name' => 'Dapur Bonda', 'ssm_no' => '[SSM NO.]', 'created_at' => '2026-11-15 12:00:00']);
        Seller::factory()->certified()->create(['business_name' => 'Kedai Kopi Sinar', 'created_at' => '2026-11-16 16:18:00']);
        Seller::factory()->create(['business_name' => 'Sweet Crumbs Bakehouse', 'ssm_no' => null, 'halal_claim' => 'certified', 'created_at' => '2026-11-14 12:00:00']);
        Seller::factory()->approved()->create(['business_name' => 'Already Approved Shop']);

        $this->get('/admin')
            ->assertSeeInOrder([
                'Sellers to approve',
                'Sweet Crumbs Bakehouse', 'Missing', 'Says certified, no file', 'Sat 14 Nov',
                'Dapur Bonda', '[SSM NO.]', 'Muslim-owned, not certified', 'Yesterday', 'Approve', 'Reject',
                'Kedai Kopi Sinar', 'Certificate uploaded', 'View', '2h ago', 'Approve',
            ])
            ->assertDontSee('Already Approved Shop');
    });

    it('shows reported listings with what customers wrote and the three decisions', function () {
        $listing = Listing::factory()->for(Seller::factory()->approved()->state(['business_name' => 'Daily Grind Café']))
            ->create(['title' => 'Sandwich & pastry pack']);
        Report::factory()->for($listing)->create(['reason' => 'food_quality', 'details' => 'Bread was stale at pickup.']);
        Report::factory()->for($listing)->create(['reason' => 'wrong_description', 'details' => null]);
        Report::factory()->for($listing)->actioned()->create(['details' => 'An old one that was dealt with']);

        $this->get('/admin')
            ->assertSeeInOrder([
                'Reported listings', 'Sandwich &amp; pastry pack · Daily Grind Café', '2 reports',
                'Food quality:', '“Bread was stale at pickup.”', 'Wrong description',
                'Hide listing', 'Warn seller', 'Dismiss',
            ], false)
            ->assertDontSee('An old one that was dealt with');
    });

    it('shows customers with a limit or strikes, limited ones first', function () {
        User::factory()->create(['name' => 'Jason Lim'])->forceFill(['no_show_strikes' => 2])->save();
        User::factory()->create(['name' => 'Farah Nordin'])->forceFill(['blocked_until' => '2026-11-18 18:18:00'])->save();
        User::factory()->create(['name' => 'Daniel Khoo'])->forceFill(['blocked_until' => '2026-11-21 18:18:00'])->save();
        User::factory()->create(['name' => 'Aina Rahman']);
        User::factory()->create(['name' => 'Old Block'])->forceFill(['blocked_until' => '2026-11-01 18:18:00'])->save();

        $this->get('/admin')
            ->assertSeeInOrder(['No-show limits', '3 no-shows = no reserving for 7 days.', 'Daniel K.', 'Limited · 5 days', 'Farah N.', 'Limited · 2 days', 'Jason L.', '2 strikes'])
            ->assertDontSee('Aina R.')
            ->assertDontSee('Old B.');
    });

    it('adds up the platform totals', function () {
        $shop = Seller::factory()->approved()->create();
        Seller::factory()->approved()->create();
        Seller::factory()->create();
        [$aina, $hafiz] = User::factory()->count(2)->create();

        $listing = Listing::factory()->for($shop)->closed()->create(['original_price_sen' => 2000, 'price_sen' => 800]);
        Reservation::factory()->for($listing)->collected()->create(['customer_id' => $aina->id, 'quantity' => 2]);
        Reservation::factory()->for($listing)->noShow()->create(['customer_id' => $hafiz->id, 'quantity' => 1]);
        Donation::factory()->for($listing)->create(['quantity' => 4]);
        Donation::factory()->for($listing)->create(['quantity' => 9, 'status' => 'declined']);

        $this->get('/admin')->assertSeeInOrder([
            'Platform totals',
            'Customers', '2',
            'Approved sellers', '2',
            'Meals rescued', '2',
            'Saved by customers', 'RM24',
            'Portions to food banks', '4',
        ]);
    });

    it('has calm empty states when nothing needs a decision', function () {
        $this->get('/admin')
            ->assertSee('No shops are waiting')
            ->assertSee('No open reports')
            ->assertSee('Nobody has a strike right now.');
    });

    it('matches the mockup on the seeded demo data', function () {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::firstWhere('email', 'admin@foodloop.test'))->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Sellers to approve', '3', 'Open reports', '1'])
            ->assertSeeInOrder(['Sweet Crumbs Bakehouse', 'Missing', 'Says certified, no file', 'Dapur Bonda', 'Kedai Kopi Sinar', 'Certificate uploaded'])
            ->assertSeeInOrder(['Sandwich &amp; pastry pack · Daily Grind Café', '1 report', '“Bread was stale at pickup.”'], false)
            ->assertSeeInOrder(['Daniel K.', 'Limited · 5 days', 'Farah N.', 'Limited · 2 days', 'Jason L.', '2 strikes']);
    });
});

describe('approving shops', function () {
    it('approves a shop that checks out, and it can then publish', function () {
        $seller = Seller::factory()->create(['business_name' => 'Dapur Bonda']);

        $this->from('/admin')->post(route('admin.sellers.approve', $seller))
            ->assertRedirect('/admin')
            ->assertSessionHas('status', 'Dapur Bonda is approved and can put listings live.');

        expect($seller->fresh()->status)->toBe(SellerStatus::Approved)
            ->and($seller->fresh()->approved_at->equalTo(now()))->toBeTrue()
            ->and($seller->user->can('publish', Listing::factory()->draft()->for($seller)->create()))->toBeTrue();

        $this->get('/admin')->assertSee('Dapur Bonda is approved and can put listings live.');
    });

    it('refuses to approve a shop with no SSM number, or a "certified" shop with no certificate', function (array $state, string $why) {
        $seller = Seller::factory()->create($state);

        $this->from('/admin')->post(route('admin.sellers.approve', $seller))
            ->assertRedirect('/admin')
            ->assertSessionHasErrors(['admin' => $why]);

        expect($seller->fresh()->status)->toBe(SellerStatus::Pending);

        // The page doesn't offer the button either; it says why.
        $this->get('/admin/sellers')->assertSee($why)->assertDontSee('Approve<span', false);
    })->with([
        'no SSM number' => [['ssm_no' => null], 'This shop has no SSM number yet, so it can’t be approved.'],
        'certified, no file' => [['halal_claim' => 'certified', 'halal_cert_path' => null], 'This shop says it is halal certified but hasn’t uploaded a certificate, so it can’t be approved.'],
    ]);

    it('rejects a waiting shop', function () {
        $seller = Seller::factory()->create(['business_name' => 'Sweet Crumbs Bakehouse']);

        $this->post(route('admin.sellers.reject', $seller))->assertSessionHas('status', 'Sweet Crumbs Bakehouse was rejected.');

        expect($seller->fresh()->status)->toBe(SellerStatus::Rejected);
    });

    it('suspends an approved shop, which takes its listings off the deals page', function () {
        $listing = dealAt(0.4, ['title' => 'Curry puffs × 4']);

        $this->get('/')->assertSee('Curry puffs × 4');

        $this->post(route('admin.sellers.suspend', $listing->seller));

        expect($listing->seller->fresh()->status)->toBe(SellerStatus::Suspended);
        $this->get('/')->assertDontSee('Curry puffs × 4');
    });

    it('can approve a suspended or rejected shop again', function (string $state) {
        $seller = Seller::factory()->{$state}()->create();

        $this->post(route('admin.sellers.approve', $seller));

        expect($seller->fresh()->status)->toBe(SellerStatus::Approved);
    })->with(['suspended', 'rejected']);

    it('explains decisions that make no sense', function (string $state, string $action, string $why) {
        $seller = Seller::factory()->{$state}()->create();

        $this->post(route("admin.sellers.{$action}", $seller))->assertSessionHasErrors(['admin' => $why]);
    })->with([
        ['approved', 'approve', 'This shop is already approved.'],
        ['approved', 'reject', 'Only a shop that is waiting for approval can be rejected. To stop an approved shop, suspend it.'],
        ['pending', 'suspend', 'Only an approved shop can be suspended.'],
    ]);

    it('groups every shop by status on the Sellers page with the right buttons', function () {
        Seller::factory()->create(['business_name' => 'Waiting Warung']);
        Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
        Seller::factory()->suspended()->create(['business_name' => 'Stopped Stall']);

        $this->get('/admin/sellers')
            ->assertOk()
            ->assertSeeInOrder([
                'Waiting for approval', 'Waiting Warung', 'Approve', 'Reject',
                'Approved', 'Roti Lane Bakery', 'Suspend',
                'Suspended', 'Stopped Stall', 'Approve again',
            ])
            ->assertDontSee('Rejected');
    });
});

describe('the halal certificate', function () {
    beforeEach(function () {
        Storage::fake('local');
        Storage::disk('local')->put('halal-certs/cert.pdf', '%PDF-1.4 test certificate');
    });

    it('shows the uploaded certificate to an admin', function () {
        $seller = Seller::factory()->create(['halal_claim' => 'certified', 'halal_cert_path' => 'halal-certs/cert.pdf']);

        $response = $this->get(route('admin.sellers.certificate', $seller))->assertOk();

        expect($response->streamedContent())->toBe('%PDF-1.4 test certificate');
    });

    it('answers "not found" when there is no certificate or the file is gone', function (?string $path) {
        $seller = Seller::factory()->create(['halal_cert_path' => $path]);

        $this->get(route('admin.sellers.certificate', $seller))->assertNotFound();
    })->with([null, 'halal-certs/missing.pdf']);
});

describe('acting on reports', function () {
    beforeEach(function () {
        $this->listing = dealAt(0.4, ['title' => 'Sandwich & pastry pack', 'quantity_total' => 5, 'quantity_available' => 5], 'Daily Grind Café');
        $this->first = Report::factory()->for($this->listing)->create(['reason' => 'food_quality', 'details' => 'Bread was stale at pickup.']);
        $this->second = Report::factory()->for($this->listing)->create(['reason' => 'halal_info', 'details' => null]);
    });

    it('hides a listing: off the site, waiting reservations cancelled and refunded, reports closed', function () {
        $paid = reservations()->reserve($customer = User::factory()->create(), $this->listing, 2, PaymentMethod::PayNow);
        $collected = Reservation::factory()->for($this->listing)->collected()->create();

        $this->from('/admin/reports')->post(route('admin.reports.hide', $this->listing))
            ->assertRedirect('/admin/reports')
            ->assertSessionHas('status', '“Sandwich & pastry pack” is taken down. 1 waiting reservation was cancelled and refunded.');

        expect($this->listing->fresh()->status)->toBe(ListingStatus::Hidden)
            ->and($paid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($paid->fresh()->late_cancel)->toBeFalse()
            ->and($paid->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($customer->fresh()->no_show_strikes)->toBe(0)
            ->and($collected->fresh()->status)->toBe(ReservationStatus::Collected)
            ->and($this->first->fresh()->status)->toBe(ReportStatus::Actioned)
            ->and($this->first->fresh()->resolution)->toBe(ReportResolution::ListingHidden)
            ->and($this->first->fresh()->resolver->is($this->admin))->toBeTrue()
            ->and($this->second->fresh()->status)->toBe(ReportStatus::Actioned);

        // Gone for the public, and nobody can reserve it.
        expect(Listing::open()->count())->toBe(0);
        $this->get('/admin/reports')->assertSee('is taken down.');
        $this->get('/')->assertDontSee('Sandwich & pastry pack');
        $this->actingAs($customer)->get(route('listings.show', $this->listing))->assertNotFound();
        expectRefusal(
            fn () => reservations()->reserve(User::factory()->create(), $this->listing->fresh(), 1, PaymentMethod::PayNow),
            RefusalReason::ListingNotLive,
        );
    });

    it('does not let the seller put a hidden listing back or edit it', function () {
        $this->post(route('admin.reports.hide', $this->listing));
        $owner = $this->listing->seller->user;

        $this->actingAs($owner)->post(route('seller.listings.publish', $this->listing))->assertSessionHasErrors('listing');
        $this->actingAs($owner)->post(route('seller.listings.pause', $this->listing))->assertSessionHasErrors('listing');

        expect($this->listing->fresh()->status)->toBe(ListingStatus::Hidden);

        $this->actingAs($owner)->get(route('seller.listings.index'))->assertSeeInOrder(['Taken down by FoodLoop', 'Sandwich &amp; pastry pack'], false);
    });

    it('tells the seller on their dashboard, with what customers reported but not who', function () {
        $this->post(route('admin.reports.hide', $this->listing));

        $this->actingAs($this->listing->seller->user)->get('/seller')
            ->assertSeeInOrder(['FoodLoop took down “Sandwich &amp; pastry pack” after a customer report.', 'Food quality: “Bread was stale at pickup.”', 'Halal information'], false)
            ->assertDontSee($this->first->reporter->name);
    });

    it('warns the seller and leaves the listing up', function () {
        $this->post(route('admin.reports.warn', $this->listing))
            ->assertSessionHas('status', 'Daily Grind Café will see a warning about “Sandwich & pastry pack” on their dashboard.');

        expect($this->listing->fresh()->status)->toBe(ListingStatus::Live)
            ->and($this->first->fresh()->resolution)->toBe(ReportResolution::SellerWarned)
            ->and($this->second->fresh()->status)->toBe(ReportStatus::Actioned);

        $this->actingAs($this->listing->seller->user)->get('/seller')
            ->assertSeeInOrder(['Warning from FoodLoop about “Sandwich &amp; pastry pack”.', 'Bread was stale at pickup.'], false);

        // Another shop sees nothing.
        $this->actingAs(Seller::factory()->approved()->create()->user)->get('/seller')->assertDontSee('Warning from FoodLoop');
    });

    it('stops showing the seller a warning after two weeks', function () {
        $this->post(route('admin.reports.warn', $this->listing));
        $this->travel(15)->days();

        $this->actingAs($this->listing->seller->user)->get('/seller')->assertDontSee('Warning from FoodLoop');
    });

    it('dismisses reports without touching the listing or telling the seller', function () {
        $this->post(route('admin.reports.dismiss', $this->listing))->assertSessionHas('status', '2 reports dismissed.');

        expect($this->listing->fresh()->status)->toBe(ListingStatus::Live)
            ->and($this->first->fresh()->status)->toBe(ReportStatus::Dismissed)
            ->and($this->first->fresh()->resolution)->toBeNull();

        $this->actingAs($this->listing->seller->user)->get('/seller')->assertDontSee('FoodLoop took down')->assertDontSee('Warning from FoodLoop');
    });

    it('explains when there is nothing to act on', function (string $action, string $why) {
        $this->post(route('admin.reports.dismiss', $this->listing));

        $this->post(route("admin.reports.{$action}", $this->listing))->assertSessionHasErrors(['admin' => $why]);
    })->with([
        ['warn', 'There is no open report on this listing to warn the seller about.'],
        ['dismiss', 'There is no open report on this listing.'],
    ]);

    it('refuses to hide a listing twice', function () {
        $this->post(route('admin.reports.hide', $this->listing));

        $this->post(route('admin.reports.hide', $this->listing))
            ->assertSessionHasErrors(['admin' => 'This listing has already been taken down.']);
    });

    it('lists open reports first and then what was decided, on the Reports page', function () {
        $other = Listing::factory()->create(['title' => 'Old kuih']);
        Report::factory()->for($other)->create(['details' => 'Too sweet']);
        $this->post(route('admin.reports.dismiss', $other));
        $this->travel(5)->minutes();
        $this->post(route('admin.reports.warn', $this->listing));
        Report::factory()->for(Listing::factory()->create(['title' => 'Fresh complaint']))->create();

        $this->get('/admin/reports')
            ->assertOk()
            ->assertSeeInOrder(['Open', 'Fresh complaint', 'Hide listing', 'Dealt with', 'Sandwich &amp; pastry pack', 'by Admin Aziz', 'Seller warned', 'Old kuih', 'Too sweet', 'Dismissed'], false);
    });
});

describe('customers and no-show limits', function () {
    it('lists customers with strikes or a limit and nobody else', function () {
        User::factory()->create(['name' => 'Daniel Khoo', 'email' => 'daniel@example.com'])->forceFill(['blocked_until' => '2026-11-21 18:18:00'])->save();
        User::factory()->create(['name' => 'Jason Lim'])->forceFill(['no_show_strikes' => 1])->save();
        User::factory()->create(['name' => 'Aina Rahman']);
        User::factory()->seller()->create(['name' => 'A Seller'])->forceFill(['no_show_strikes' => 2])->save();

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSeeInOrder(['Daniel Khoo', 'daniel@example.com', 'Limited · 5 days', 'until Sat 21 Nov, 6:18 PM', 'Lift limit', 'Jason Lim', '1 strike', 'Clear strikes'])
            ->assertDontSee('Aina Rahman')
            ->assertDontSee('A Seller');
    });

    it('lifts a limit so the customer can reserve again from zero strikes', function () {
        $customer = User::factory()->create(['name' => 'Daniel Khoo']);
        $customer->forceFill(['blocked_until' => now()->addDays(5), 'no_show_strikes' => 1])->save();

        $this->post(route('admin.customers.lift-limit', $customer))
            ->assertSessionHas('status', 'Daniel Khoo can reserve again and starts from zero strikes.');

        expect($customer->fresh()->isBlocked())->toBeFalse()
            ->and($customer->fresh()->no_show_strikes)->toBe(0)
            ->and(reservations()->reserve($customer->fresh(), dealAt(0.4), 1, PaymentMethod::PayNow)->status)->toBe(ReservationStatus::Reserved);
    });

    it('explains when there is nothing to lift, and only works for customers', function () {
        $this->post(route('admin.customers.lift-limit', User::factory()->create()))
            ->assertSessionHasErrors(['admin' => 'This customer has no strikes and no limit.']);

        $this->post(route('admin.customers.lift-limit', User::factory()->seller()->create()))
            ->assertSessionHasErrors(['admin' => 'Only customer accounts have no-show limits.']);
    });
});
