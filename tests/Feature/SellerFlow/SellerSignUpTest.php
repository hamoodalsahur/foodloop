<?php

// "Sell on FoodLoop": the separate sign-up for shops (owner's decision, 14 Nov).

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->form = fn (array $overrides = []) => [
        'name' => 'Siti Aminah',
        'email' => 'siti@rotilane.test',
        'password' => 'bread-every-evening',
        'password_confirmation' => 'bread-every-evening',
        'business_name' => 'Roti Lane Bakery',
        'ssm_no' => '202301045678',
        'phone' => '012-345 6789',
        'address' => 'Lot 12, Jalan Kampus, Shah Alam',
        'halal_claim' => 'muslim_owned',
        ...$overrides,
    ];
});

it('shows the shop sign-up page, linked from log in and register', function () {
    $this->get(route('seller.sign-up'))
        ->assertOk()
        ->assertSeeInOrder(['Sell on FoodLoop', 'Your name', 'Email', 'Password', 'Shop name', 'SSM registration number', 'Phone', 'Address', 'Halal status']);

    $this->get('/login')->assertSee(route('seller.sign-up'));
    $this->get('/register')->assertSee(route('seller.sign-up'));
});

it('creates a seller account with a shop waiting for approval, and logs it in', function () {
    $this->post(route('seller.sign-up.store'), ($this->form)())
        ->assertRedirect(route('seller.dashboard'))
        ->assertSessionHas('status', 'Your shop is set up and waiting for approval. You can write drafts meanwhile.');

    $user = User::where('email', 'siti@rotilane.test')->sole();
    expect($user->role)->toBe(UserRole::Seller)
        ->and($user->seller)->business_name->toBe('Roti Lane Bakery')
        ->and($user->seller->status)->toBe(SellerStatus::Pending)
        ->and($user->seller->halal_cert_path)->toBeNull();
    $this->assertAuthenticatedAs($user);

    $this->get(route('seller.dashboard'))->assertSee('Your shop is waiting for approval');
});

it('needs a certificate for “halal certified”, and keeps it private', function () {
    $this->post(route('seller.sign-up.store'), ($this->form)(['halal_claim' => 'certified']))
        ->assertSessionHasErrors(['halal_certificate' => 'Upload your JAKIM certificate to be listed as halal certified.']);
    expect(Seller::count())->toBe(0);

    $this->post(route('seller.sign-up.store'), ($this->form)([
        'halal_claim' => 'certified',
        'halal_certificate' => UploadedFile::fake()->create('jakim.pdf', 200, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $path = Seller::sole()->halal_cert_path;
    expect($path)->toStartWith('halal-certs/');
    Storage::disk('local')->assertExists($path);
    // Not approved yet: an admin checks the certificate first (Stage 7).
    expect(Seller::sole()->hasApprovedHalalCertificate())->toBeFalse();
});

it('drops a certificate sent with a different halal status', function () {
    $this->post(route('seller.sign-up.store'), ($this->form)([
        'halal_certificate' => UploadedFile::fake()->create('jakim.pdf', 200, 'application/pdf'),
    ]));

    expect(Seller::sole()->halal_cert_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('halal-certs'))->toBe([]);
});

it('refuses missing shop details and a taken email', function () {
    User::factory()->create(['email' => 'siti@rotilane.test']);

    $this->post(route('seller.sign-up.store'), ($this->form)(['business_name' => '', 'ssm_no' => '']))
        ->assertSessionHasErrors([
            'email',
            'business_name' => 'Enter your shop’s name.',
            'ssm_no' => 'Enter your SSM registration number.',
        ]);

    expect(Seller::count())->toBe(0);
});

it('cannot be used to make an admin', function () {
    $this->post(route('seller.sign-up.store'), ($this->form)(['role' => 'admin', 'status' => 'approved']));

    expect(User::where('email', 'siti@rotilane.test')->sole()->role)->toBe(UserRole::Seller)
        ->and(Seller::sole()->status)->toBe(SellerStatus::Pending);
});

it('sends someone already logged in away from the sign-up', function () {
    $this->actingAs(User::factory()->create())->get(route('seller.sign-up'))->assertRedirect();
});
