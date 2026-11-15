<?php

// The rest of mockup c10: "Ask for info" on a waiting shop, the Listings page
// and the Food banks page.

use App\Enums\DonationStatus;
use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Aziz']);
    $this->actingAs($this->admin);
});

it('keeps everyone but admins out of the new pages and buttons', function (string $method, string $url) {
    $seller = Seller::factory()->create();
    $listing = Listing::factory()->create();
    $donation = Donation::factory()->create();
    $customer = User::factory()->create();
    $url = str_replace(['{seller}', '{partner}', '{donation}'], [$seller->id, $donation->food_bank_partner_id, $donation->id], $url);

    auth()->logout();
    $this->{$method}($url)->assertRedirect('/login');

    $this->actingAs($customer)->{$method}($url)->assertForbidden();
    $this->actingAs($listing->seller->user)->{$method}($url)->assertForbidden();
})->with([
    ['get', '/admin/listings'],
    ['get', '/admin/food-banks'],
    ['post', '/admin/sellers/{seller}/ask'],
    ['put', '/admin/food-banks/{partner}'],
    ['post', '/admin/donations/{donation}/settle'],
]);

it('has the six menu items of the mockup', function () {
    $this->get('/admin')->assertSeeInOrder(['Overview', 'Sellers', 'Listings', 'Reports', 'Customers', 'Food banks']);
});

describe('ask for info', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->shop = Seller::factory()->pending()->create([
            'business_name' => 'Sweet Crumbs Bakehouse',
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => null,
        ]);
        $this->ask = fn (string $question = 'Please upload your JAKIM certificate.') => $this->actingAs($this->admin)
            ->from('/admin/sellers')->post(route('admin.sellers.ask', $this->shop), ['question' => $question]);
    });

    it('offers it for shops that are waiting, also when they can’t be approved yet', function () {
        Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);

        $this->get('/admin')->assertSeeInOrder([
            'Sellers to approve', 'Sweet Crumbs Bakehouse', 'hasn’t uploaded a certificate',
            'Ask for info', 'action="'.route('admin.sellers.ask', $this->shop).'"', 'What do you need from Sweet Crumbs Bakehouse?', 'Send question',
            'Reject',
        ], false);

        // Once per shop that is waiting (the list is on the page twice: table and phone cards).
        expect(substr_count($this->get('/admin/sellers')->getContent(), 'Send question'))->toBe(2);
    });

    it('saves the question, tells the shop, and shows the admin it is waiting', function () {
        ($this->ask)()->assertRedirect('/admin/sellers')
            ->assertSessionHas('status', 'Sent to Sweet Crumbs Bakehouse. You’ll get a notification when they update their details.');

        expect($this->shop->fresh())
            ->info_request->toBe('Please upload your JAKIM certificate.')
            ->info_requested_at->equalTo(now())->toBeTrue()
            ->status->toBe(SellerStatus::Pending)
            ->and(messageFor($this->shop->user, 'seller.info_requested'))->toBe([
                'kind' => 'seller.info_requested',
                'title' => 'FoodLoop needs more info to approve your shop',
                'body' => '“Please upload your JAKIM certificate.” Update your shop profile to answer.',
                'url' => '/seller/profile',
            ]);

        $this->travel(2)->hours();

        $this->get('/admin/sellers')->assertSeeInOrder([
            'Sweet Crumbs Bakehouse', 'Asked 2 hours ago: “Please upload your JAKIM certificate.” · waiting for the shop', 'Ask again',
        ]);
    });

    it('needs a question, and only works on a shop that is waiting', function () {
        ($this->ask)('')->assertSessionHasErrors(['question' => 'Write what you need from the shop.']);
        ($this->ask)(str_repeat('a', 301))->assertSessionHasErrors(['question' => 'Keep the question under 300 characters.']);

        // The reason is shown on the page the admin comes back to.
        ($this->ask)('');
        $this->get('/admin/sellers')->assertSee('Write what you need from the shop.');

        $approved = Seller::factory()->approved()->create();
        $this->post(route('admin.sellers.ask', $approved), ['question' => 'Anything?'])
            ->assertSessionHasErrors(['admin' => 'Only a shop that is waiting for approval can be asked for more info.']);

        expect($this->shop->fresh()->info_request)->toBeNull()
            ->and(messageKinds($approved->user))->toBe([]);
    });

    it('shows the shop the question on its pages until it answers', function () {
        ($this->ask)();
        $this->travel(5)->minutes();

        $this->actingAs($this->shop->user)->get(route('seller.dashboard'))->assertSeeInOrder([
            'Your shop is waiting for approval',
            'FoodLoop asked 5 minutes ago:', '“Please upload your JAKIM certificate.”',
            'href="'.route('seller.profile.edit').'"', 'Update shop profile',
        ], false);

        $this->get(route('seller.profile.edit'))->assertSeeInOrder([
            'FoodLoop asked', 'Update your details below and save. The admin is told straight away.',
            'JAKIM certificate', 'No certificate on file yet.',
        ]);
    });

    it('lets the shop answer by saving its profile with the certificate: the admin is told and can approve', function () {
        ($this->ask)();

        $this->actingAs($this->shop->user)->put(route('seller.profile.update'), [
            'business_name' => 'Sweet Crumbs Bakehouse',
            'ssm_no' => '202601099999',
            'halal_certificate' => UploadedFile::fake()->create('jakim.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('seller.profile.edit'))
            ->assertSessionHas('status', 'Shop details saved. The admin has been told you’ve updated them.');

        $shop = $this->shop->fresh();

        expect($shop->info_request)->toBeNull()
            ->and($shop->info_requested_at)->toBeNull()
            ->and($shop->halal_cert_path)->toStartWith('halal-certs/')
            ->and(Storage::disk('local')->exists($shop->halal_cert_path))->toBeTrue()
            ->and(messageFor($this->admin, 'admin.shop_answered'))->toMatchArray([
                'title' => 'Sweet Crumbs Bakehouse updated its details',
                'url' => '/admin/sellers',
            ]);

        $this->get(route('seller.dashboard'))->assertDontSee('FoodLoop asked');

        // Now nothing stands in the way of approving it.
        $this->actingAs($this->admin)->post(route('admin.sellers.approve', $shop))
            ->assertSessionHas('status', 'Sweet Crumbs Bakehouse is approved and can put listings live.');
    });

    it('doesn’t tell the admins about a profile save nobody asked for', function () {
        $this->actingAs($this->shop->user)->put(route('seller.profile.update'), ['business_name' => 'Sweet Crumbs'])
            ->assertSessionHas('status', 'Shop details saved.');

        expect(messageKinds($this->admin))->toBe([]);
    });

    it('replaces an earlier certificate and removes the old file', function () {
        Storage::disk('local')->put('halal-certs/old.pdf', 'old');
        $this->shop->update(['halal_cert_path' => 'halal-certs/old.pdf']);

        $this->actingAs($this->shop->user)->get(route('seller.profile.edit'))->assertSee('A certificate is on file. Choose a file to replace it.');
        $this->put(route('seller.profile.update'), [
            'business_name' => 'Sweet Crumbs Bakehouse',
            'halal_certificate' => UploadedFile::fake()->image('jakim.jpg'),
        ])->assertSessionHasNoErrors();

        expect(Storage::disk('local')->exists('halal-certs/old.pdf'))->toBeFalse()
            ->and($this->shop->fresh()->halal_cert_path)->not->toBe('halal-certs/old.pdf');
    });

    it('checks the certificate file', function () {
        $this->actingAs($this->shop->user)->put(route('seller.profile.update'), [
            'business_name' => 'Sweet Crumbs Bakehouse',
            'halal_certificate' => UploadedFile::fake()->create('cert.docx', 100),
        ])->assertSessionHasErrors(['halal_certificate' => 'The certificate has to be a PDF, JPG or PNG.']);

        expect($this->shop->fresh()->halal_cert_path)->toBeNull();
    });

    it('takes no certificate once the shop is approved, or from a shop that doesn’t claim to be certified', function (Closure $shop) {
        $shop = $shop();

        $this->actingAs($shop->user)->get(route('seller.profile.edit'))->assertDontSee('JAKIM certificate');
        $this->put(route('seller.profile.update'), [
            'business_name' => $shop->business_name,
            'halal_certificate' => UploadedFile::fake()->create('jakim.pdf', 200, 'application/pdf'),
        ])->assertSessionHasErrors(['halal_certificate' => 'Your certificate can only be changed by an admin now.']);

        expect($shop->fresh()->halal_cert_path)->toBe($shop->halal_cert_path);
    })->with([
        'approved and certified' => [fn () => Seller::factory()->approved()->certified()->create()],
        'waiting, Muslim-owned' => [fn () => Seller::factory()->pending()->create()],
    ]);

    it('forgets the question once the shop is approved or rejected', function (string $decision) {
        $shop = Seller::factory()->pending()->create();
        $this->post(route('admin.sellers.ask', $shop), ['question' => 'Is the SSM number right?']);

        $this->post(route("admin.sellers.{$decision}", $shop))->assertSessionHasNoErrors();

        expect($shop->fresh()->info_request)->toBeNull();
    })->with(['approve', 'reject']);
});

describe('listings page', function () {
    beforeEach(function () {
        $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
        $this->bundle = Listing::factory()->for($this->shop)->window(42, 162)->create([
            'title' => 'Bakery Surprise Bundle', 'original_price_sen' => 2000, 'price_sen' => 800, 'quantity_total' => 5, 'quantity_available' => 5,
        ]);
    });

    it('lists what is on FoodLoop now, soonest pickup first, with reports and waiting reservations', function () {
        Listing::factory()->for($this->shop)->window(10, 60)->soldOut()->create(['title' => 'Croissants']);
        Listing::factory()->for($this->shop)->window(200, 300)->paused()->create(['title' => 'Curry puffs']);
        Listing::factory()->for($this->shop)->draft()->create(['title' => 'A draft']);
        Listing::factory()->for($this->shop)->closed()->create(['title' => 'Yesterday’s bread']);
        Report::factory()->for($this->bundle)->count(2)->create();
        reservations()->reserve(User::factory()->create(), $this->bundle, 2, PaymentMethod::PayNow);

        $this->get('/admin/listings')->assertOk()
            ->assertSeeInOrder([
                'On FoodLoop now', 'Taken down',
                'Croissants · Roti Lane Bakery', 'Sold out',
                'Bakery Surprise Bundle · Roti Lane Bakery', 'RM8 (was RM20)', '3 of 5 left', 'Pickup Today 7–9 PM',
                'Live', '2 open reports', '2 reserved, waiting for pickup', 'Hide listing',
                'Curry puffs · Roti Lane Bakery', 'Paused',
            ])
            ->assertDontSee('A draft')->assertDontSee('Yesterday’s bread');
    });

    it('hides a listing nobody reported: reservations cancelled, customers and shop told', function () {
        $customer = User::factory()->create();
        $reservation = reservations()->reserve($customer, $this->bundle, 1, PaymentMethod::PayNow);

        $this->from('/admin/listings')->post(route('admin.reports.hide', $this->bundle))
            ->assertRedirect('/admin/listings')
            ->assertSessionHas('status', '“Bakery Surprise Bundle” is taken down. 1 waiting reservation was cancelled and refunded.');

        expect($this->bundle->fresh()->status)->toBe(ListingStatus::Hidden)
            ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and(messageFor($customer, 'reservation.cancelled_by_foodloop'))->not->toBeNull()
            ->and(messageFor($this->shop->user, 'seller.takedown')['body'])
            ->toBe('An admin took this listing off FoodLoop. 1 reservation was cancelled for the customers.');

        $this->get('/admin/listings')->assertDontSee('Bakery Surprise Bundle · Roti Lane Bakery')->assertSee('No listings right now');
        $this->get('/admin/listings?show=hidden')
            ->assertSeeInOrder(['Bakery Surprise Bundle · Roti Lane Bakery', 'Taken down 16 Nov, 6:18 PM'])
            ->assertDontSee('Hide listing');
    });

    it('says so when nothing was taken down', function () {
        $this->get('/admin/listings?show=hidden')->assertSee('Nothing has been taken down');
    });
});

describe('food banks page', function () {
    beforeEach(function () {
        $this->partner = FoodBankPartner::factory()->create(['name' => '[FOOD BANK PARTNER NAME]', 'contact' => '[FOOD BANK CONTACT]']);
        $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
        $this->offer = fn (string $title, int $quantity, DonationStatus $status = DonationStatus::Offered) => Donation::factory()
            ->for(Listing::factory()->for($this->shop)->closed()->create(['title' => $title]))
            ->for($this->partner, 'partner')
            ->create(['quantity' => $quantity, 'status' => $status]);
    });

    it('shows the partner, the totals and every offer, the waiting ones first', function () {
        $this->travelTo('2026-11-15 21:30:00');
        ($this->offer)('Croissants', 3, DonationStatus::Collected);
        $this->travelTo('2026-11-16 18:18:00');
        ($this->offer)('Nasi lemak', 2, DonationStatus::Declined);
        ($this->offer)('Curry puffs', 4);

        $this->get('/admin/food-banks')->assertOk()
            ->assertSeeInOrder([
                'Food banks',
                'Portions waiting for the food bank', '4', 'Portions collected', '3', 'Portions declined', '2',
                'Partner', 'This is still the placeholder.', 'value="[FOOD BANK PARTNER NAME]"', 'value="[FOOD BANK CONTACT]"', 'Save details',
                'Leftover offers',
                'Curry puffs', '4 portions · Roti Lane Bakery · to [FOOD BANK PARTNER NAME] · offered 16 Nov, 6:18 PM', 'Mark collected', 'Declined',
            ], false)
            ->assertSeeInOrder(['Curry puffs', 'Croissants', '3 portions']);
    });

    it('says so when nothing has been offered', function () {
        $this->get('/admin/food-banks')->assertSee('No leftovers offered yet');
    });

    it('saves the real partner’s details', function () {
        $this->from('/admin/food-banks')->put(route('admin.food-banks.update', $this->partner), [
            'name' => 'Kechara Soup Kitchen', 'contact' => 'Puan Lim · 03-1234 5678',
        ])->assertRedirect('/admin/food-banks')->assertSessionHas('status', 'Food bank details saved.');

        expect($this->partner->fresh())->name->toBe('Kechara Soup Kitchen')->contact->toBe('Puan Lim · 03-1234 5678');

        $this->get('/admin/food-banks')->assertSee('value="Kechara Soup Kitchen"', false)->assertDontSee('This is still the placeholder.');

        $this->put(route('admin.food-banks.update', $this->partner), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The food bank needs a name.']);
    });

    it('records that the food bank collected or declined an offer, once, and tells the shop', function (string $outcome, DonationStatus $status, string $flash) {
        $donation = ($this->offer)('Curry puffs', 4);

        $this->from('/admin/food-banks')->post(route('admin.donations.settle', $donation), ['outcome' => $outcome])
            ->assertRedirect('/admin/food-banks')->assertSessionHas('status', $flash);

        expect($donation->fresh()->status)->toBe($status)
            ->and(messageFor($this->shop->user, 'seller.donation_settled'))->not->toBeNull();

        $this->get('/admin/food-banks')->assertDontSee('Mark collected');

        // Decided once.
        $this->post(route('admin.donations.settle', $donation), ['outcome' => 'collected'])
            ->assertSessionHasErrors(['admin' => 'This offer was already marked '.strtolower($status->label()).'.']);
    })->with([
        ['collected', DonationStatus::Collected, 'Marked as collected: 4 × Curry puffs. The shop has been told.'],
        ['declined', DonationStatus::Declined, 'Marked as declined: 4 × Curry puffs. The shop has been told.'],
    ]);

    it('only accepts collected or declined', function (array $form) {
        $donation = ($this->offer)('Curry puffs', 4);

        $this->post(route('admin.donations.settle', $donation), $form)->assertSessionHasErrors(['outcome' => 'Choose collected or declined.']);

        expect($donation->fresh()->status)->toBe(DonationStatus::Offered);
    })->with([[['outcome' => 'offered']], [['outcome' => 'eaten']], [[]]]);
});
