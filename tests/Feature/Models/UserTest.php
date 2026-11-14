<?php

use App\Enums\UserRole;
use App\Models\User;

it('makes every new account a customer with no strikes', function () {
    $user = User::create(['name' => 'Aina', 'email' => 'aina@example.com', 'password' => 'secret-password']);

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->fresh()->role)->toBe(UserRole::Customer)
        ->and($user->fresh()->no_show_strikes)->toBe(0)
        ->and($user->fresh()->blocked_until)->toBeNull()
        ->and($user->isCustomer())->toBeTrue()
        ->and($user->isBlocked())->toBeFalse();
});

it('does not let a sign-up form choose its own role, strikes or block', function () {
    $this->post('/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'role' => 'admin',
        'no_show_strikes' => 0,
    ]);

    $user = User::where('email', 'sneaky@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Customer);

    $user->update(['role' => 'admin', 'no_show_strikes' => 99, 'blocked_until' => now()->addYear()]);

    expect($user->fresh()->role)->toBe(UserRole::Customer)
        ->and($user->fresh()->no_show_strikes)->toBe(0)
        ->and($user->fresh()->blocked_until)->toBeNull();
});

it('knows which role an account has', function () {
    expect(User::factory()->seller()->create()->isSeller())->toBeTrue()
        ->and(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->create()->isAdmin())->toBeFalse();
});

it('treats a user as blocked only while blocked_until is in the future', function () {
    $user = User::factory()->blocked(days: 7)->create();

    expect($user->isBlocked())->toBeTrue()
        ->and($user->no_show_strikes)->toBe(0);

    $this->travel(8)->days();

    expect($user->fresh()->isBlocked())->toBeFalse();
});
