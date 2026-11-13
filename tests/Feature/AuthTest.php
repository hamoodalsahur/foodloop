<?php

use App\Models\User;

// Fortify is wired up without screens for now (they come in Stage 4), so
// these tests post straight to its form endpoints.

it('registers a new user and signs them in', function () {
    $this->post('/register', [
        'name' => 'Aina Rahman',
        'email' => 'aina@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'aina@example.com']);
});

it('does not register the same email twice', function () {
    User::factory()->create(['email' => 'aina@example.com']);

    $this->post('/register', [
        'name' => 'Aina Rahman',
        'email' => 'aina@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
});

it('signs a user in with the right password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('refuses the wrong password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
