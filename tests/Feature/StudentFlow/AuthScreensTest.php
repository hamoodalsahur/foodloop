<?php

// The log in and register screens, and where each role lands afterwards.

use App\Enums\UserRole;
use App\Models\Seller;
use App\Models\User;

it('shows the log in screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSeeInOrder(['Log in', 'Email', 'Password', 'Keep me logged in', 'Create an account'])
        ->assertSee('autocomplete="current-password"', false);
});

it('shows the register screen', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSeeInOrder(['Create your account', 'Your name', 'Email', 'Password', 'At least 8 characters.', 'Password again', 'Create account', 'Log in']);
});

it('signs a new customer up and takes them to the deals', function () {
    $this->post('/register', [
        'name' => 'Siti Aminah',
        'email' => 'siti@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('deals.index'));

    $this->assertAuthenticated();
    expect(User::firstWhere('email', 'siti@example.com')->role)->toBe(UserRole::Customer);

    $this->get('/')->assertSee('Account menu for Siti Aminah')->assertSee('Log out')->assertDontSee('Log in');
});

it('shows what is wrong on the register form and keeps what was typed', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->from('/register')->post('/register', [
        'name' => 'Siti Aminah',
        'email' => 'taken@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertRedirect('/register');

    $this->get('/register')
        ->assertSee('The email has already been taken.')
        ->assertSee('value="Siti Aminah"', false)
        ->assertSee('value="taken@example.com"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertDontSee('value="short"', false);
});

it('sends each role to its own area after logging in', function (Closure $makeUser, string $routeName) {
    $user = $makeUser();
    $user->forceFill(['password' => 'correct-horse-battery'])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect(route($routeName));
})->with([
    'customer' => [fn () => User::factory()->create(), 'deals.index'],
    'seller' => [fn () => Seller::factory()->approved()->create()->user, 'seller.dashboard'],
    'admin' => [fn () => User::factory()->admin()->create(), 'admin.dashboard'],
]);

it('brings someone back to the page they wanted after logging in', function () {
    $customer = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->get('/orders')->assertRedirect('/login');

    $this->post('/login', ['email' => $customer->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect('/orders');
});

it('shows a clear message for a wrong password and does not say which part was wrong', function () {
    $customer = User::factory()->create();

    $this->from('/login')->post('/login', ['email' => $customer->email, 'password' => 'nope'])->assertRedirect('/login');

    $this->get('/login')
        ->assertSee('These credentials do not match our records.')
        ->assertSee('value="'.$customer->email.'"', false);

    $this->assertGuest();
});

it('slows down repeated wrong passwords', function () {
    $customer = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $customer->email, 'password' => 'nope']);
    }

    $this->post('/login', ['email' => $customer->email, 'password' => 'nope'])->assertStatus(429);
});

it('logs out back to the deals', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    $this->get('/')->assertSee('Log in');
});

it('sends someone who is already logged in away from the log in screen', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
});
