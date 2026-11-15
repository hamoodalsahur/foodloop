<?php

// "Forgot your password?": ask for a link, choose a new password. The same
// two screens serve customers, sellers and admins.

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'aina@example.com', 'password' => Hash::make('the-old-password')]);
});

it('links to it from the log in screen', function () {
    $this->get('/login')->assertSeeInOrder(['Log in', 'href="'.route('password.request').'"', 'Forgot your password?', 'Create an account'], false);
});

it('shows the screen, and is honest that the prototype sends no real email', function () {
    $this->get('/forgot-password')->assertOk()->assertSeeInOrder([
        'Forgot your password?', 'Prototype', 'FoodLoop doesn’t send real email yet.', 'storage/logs/laravel.log',
        'Email', 'Send reset link', 'Back to log in',
    ]);

    // With a real mail service set up, the note is gone.
    config(['mail.default' => 'smtp']);
    $this->get('/forgot-password')->assertDontSee('Prototype');
});

it('emails a reset link to an account that exists', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'aina@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', 'We have emailed your password reset link.');

    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('says so when the email is unknown or missing', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors(['email' => 'We can\'t find a user with that email address.']);
    $this->post('/forgot-password', ['email' => ''])->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('puts a link to the reset screen in the email', function () {
    Notification::fake();
    $this->post('/forgot-password', ['email' => 'aina@example.com']);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $email) {
        $link = $email->toMail($this->user)->actionUrl;

        expect($link)->toBe(route('password.reset', ['token' => $email->token, 'email' => 'aina@example.com']));

        $this->get($link)->assertOk()->assertSeeInOrder([
            'Choose a new password', 'name="token" value="'.$email->token.'"', 'value="aina@example.com"',
            'New password', 'At least 8 characters.', 'New password again', 'Save new password',
        ], false);

        return true;
    });
});

it('saves the new password: the old one stops working and the new one logs in', function () {
    $token = Password::createToken($this->user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => 'aina@example.com',
        'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect('/login')->assertSessionHas('status', 'Your password has been reset.');

    $this->get('/login')->assertSee('Your password has been reset.');

    $this->post('/login', ['email' => 'aina@example.com', 'password' => 'the-old-password'])->assertSessionHasErrors();
    $this->assertGuest();

    $this->post('/login', ['email' => 'aina@example.com', 'password' => 'a-brand-new-password'])->assertRedirect(route('deals.index'));
    $this->assertAuthenticatedAs($this->user);
});

it('refuses a wrong or used link and a weak or mismatched password', function () {
    $token = Password::createToken($this->user);
    $form = ['token' => $token, 'email' => 'aina@example.com', 'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'];

    $this->post('/reset-password', [...$form, 'token' => 'not-the-token'])
        ->assertSessionHasErrors(['email' => 'This password reset token is invalid.']);
    $this->post('/reset-password', [...$form, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('/reset-password', [...$form, 'password_confirmation' => 'something-else'])->assertSessionHasErrors('password');

    expect(Hash::check('the-old-password', $this->user->fresh()->password))->toBeTrue();

    // A link works once.
    $this->post('/reset-password', $form)->assertSessionHasNoErrors();
    $this->post('/reset-password', [...$form, 'password' => 'yet-another-password', 'password_confirmation' => 'yet-another-password'])
        ->assertSessionHasErrors('email');
});

describe('the prototype shortcut', function () {
    // Outside APP_ENV=testing a form needs its CSRF token; these tests post without a page.
    beforeEach(fn () => $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]));

    it('shows the link on screen on a team laptop (APP_ENV=local, no real email)', function () {
        $this->app->detectEnvironment(fn () => 'local');

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'aina@example.com'])->assertSessionHas('reset_link');

        $link = session('reset_link');

        $this->get('/forgot-password')->assertSeeInOrder([
            'Prototype only', 'No real email is sent on this computer', 'href="'.e($link).'"', 'Open the reset link',
        ], false);

        // And the link really is the one that resets this account.
        $this->get($link)->assertOk()->assertSee('value="aina@example.com"', false);
    });

    it('never shows it anywhere else, or once real email is set up', function (string $environment, string $mailer) {
        $this->app->detectEnvironment(fn () => $environment);
        config(['mail.default' => $mailer]);
        config(['mail.mailers.smtp' => ['transport' => 'array']]);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'aina@example.com'])
            ->assertSessionHas('status')->assertSessionMissing('reset_link');
    })->with([
        'production, emails logged' => ['production', 'log'],
        'testing, emails logged' => ['testing', 'log'],
        'local, real email' => ['local', 'smtp'],
    ]);
});
