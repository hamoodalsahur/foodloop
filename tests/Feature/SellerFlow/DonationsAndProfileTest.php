<?php

// Offering leftovers to a food bank, the donations page, and the shop profile.

use App\Enums\DonationStatus;
use App\Enums\SellerStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;

beforeEach(function () {
    $this->travelTo('2026-11-16 22:00:00');

    $this->shop = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);
    $this->seller = $this->shop->user;
    $this->partner = FoodBankPartner::factory()->create(['name' => 'Kechara Soup Kitchen (demo)']);
    $this->listing = Listing::factory()->for($this->shop)->closed()->create([
        'title' => 'Bakery Surprise Bundle', 'offer_leftovers' => false,
        'quantity_total' => 5, 'quantity_available' => 3,
        'pickup_start' => '2026-11-16 19:00:00', 'pickup_end' => '2026-11-16 21:00:00',
    ]);
});

it('suggests offering what is left over, and offers it', function () {
    $this->actingAs($this->seller)->get(route('seller.dashboard'))
        ->assertSee('Left over at closing?')
        ->assertSee('Bakery Surprise Bundle');

    $this->actingAs($this->seller)->post(route('seller.listings.donation', $this->listing))
        ->assertSessionHas('status', 'Offered 3 to Kechara Soup Kitchen (demo). Thank you!');

    $donation = Donation::sole();
    expect($donation)->quantity->toBe(3)->status->toBe(DonationStatus::Offered)
        ->and($donation->listing_id)->toBe($this->listing->id);

    $this->actingAs($this->seller)->get(route('seller.donations.index'))
        ->assertOk()
        ->assertSeeInOrder(['Bakery Surprise Bundle', '3', 'Kechara Soup Kitchen (demo)', 'Offered']);
});

it('counts no-shows as leftovers too', function () {
    Reservation::factory()->for($this->listing)->noShow()->create(['quantity' => 2]);

    $this->actingAs($this->seller)->post(route('seller.listings.donation', $this->listing));

    expect(Donation::sole()->quantity)->toBe(5);
});

it('refuses to offer twice, too early, or while a customer still has time', function () {
    $this->actingAs($this->seller)->post(route('seller.listings.donation', $this->listing));
    $this->actingAs($this->seller)->post(route('seller.listings.donation', $this->listing))
        ->assertSessionHasErrors(['listing' => 'These leftovers have already been offered.']);

    $live = Listing::factory()->for($this->shop)->create();
    $this->actingAs($this->seller)->post(route('seller.listings.donation', $live))
        ->assertSessionHasErrors(['listing' => 'Leftovers can be offered once the listing has closed.']);

    $waiting = Listing::factory()->for($this->shop)->closed()->create(['quantity_available' => 2]);
    Reservation::factory()->for($waiting)->create();
    $this->actingAs($this->seller)->post(route('seller.listings.donation', $waiting))
        ->assertSessionHasErrors(['listing' => 'Wait until every customer has collected or missed their pickup.']);

    expect(Donation::count())->toBe(1);
});

it('keeps other shops from offering this shop’s food', function () {
    $this->actingAs(Seller::factory()->approved()->create()->user)
        ->post(route('seller.listings.donation', $this->listing))
        ->assertForbidden();
});

it('updates the shop details, but never the approval status', function () {
    $this->actingAs($this->seller)->get(route('seller.profile.edit'))
        ->assertOk()
        ->assertSee('Roti Lane Bakery');

    $this->actingAs($this->seller)->put(route('seller.profile.update'), [
        'business_name' => 'Roti Lane Bakery & Café',
        'ssm_no' => '202301045678',
        'phone' => '012-999 0000',
        'address' => 'Lot 14, Jalan Kampus',
        'status' => 'suspended',
    ])->assertSessionHas('status', 'Shop details saved.');

    expect($this->shop->fresh())
        ->business_name->toBe('Roti Lane Bakery & Café')
        ->phone->toBe('012-999 0000')
        ->status->toBe(SellerStatus::Approved);

    $this->actingAs($this->seller)->put(route('seller.profile.update'), ['business_name' => ''])
        ->assertSessionHasErrors(['business_name' => 'Your shop needs a name.']);
});
