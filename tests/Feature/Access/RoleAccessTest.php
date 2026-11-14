<?php

// Who can open which area (routes/web.php). Guests are sent to the login
// page; a signed-in user with the wrong role gets "403 not allowed".

use App\Models\Seller;
use App\Models\User;

dataset('areas', [
    'customer area' => ['/orders', 'customer'],
    'seller area' => ['/seller', 'seller'],
    'admin area' => ['/admin', 'admin'],
]);

function userWithRole(string $role): User
{
    return match ($role) {
        'customer' => User::factory()->create(),
        'seller' => Seller::factory()->approved()->create()->user,
        'admin' => User::factory()->admin()->create(),
    };
}

it('sends guests to the login page', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with('areas');

it('lets the right role in', function (string $url, string $role) {
    $this->actingAs(userWithRole($role))->get($url)->assertOk();
})->with('areas');

it('keeps every other role out', function (string $url, string $allowedRole) {
    foreach (array_diff(['customer', 'seller', 'admin'], [$allowedRole]) as $otherRole) {
        $this->actingAs(userWithRole($otherRole))->get($url)->assertForbidden();
    }
})->with('areas');

it('lets everyone, signed in or not, open the public home page', function () {
    $this->get('/')->assertOk();
    $this->actingAs(userWithRole('seller'))->get('/')->assertOk();
});

it('lets a seller who is not approved yet into the seller area, with a notice', function (string $state, string $notice) {
    $seller = Seller::factory()->{$state}()->create();

    $this->actingAs($seller->user)->get('/seller')
        ->assertOk()
        ->assertSee($notice)
        ->assertSee('listings only go live once an admin has approved your shop');
})->with([
    'pending' => ['pending', 'Your shop is waiting for approval'],
    'suspended' => ['suspended', 'Your shop is suspended'],
]);

it('shows no approval notice to an approved seller', function () {
    $seller = Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']);

    $this->actingAs($seller->user)->get('/seller')
        ->assertOk()
        ->assertSee('Roti Lane Bakery')
        ->assertSee('Verified')
        ->assertDontSee('admin has approved your shop');
});

it('does not trust a role sent with the request', function () {
    $this->actingAs(userWithRole('customer'))
        ->get('/admin?role=admin')
        ->assertForbidden();
});

it('accepts several roles on one route', function () {
    Route::middleware(['web', 'auth', 'role:seller,admin'])->get('/_test/staff', fn () => 'ok');

    $this->actingAs(userWithRole('seller'))->get('/_test/staff')->assertOk();
    $this->actingAs(userWithRole('admin'))->get('/_test/staff')->assertOk();
    $this->actingAs(userWithRole('customer'))->get('/_test/staff')->assertForbidden();
});
