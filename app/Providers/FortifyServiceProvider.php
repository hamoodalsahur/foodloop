<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // After login or sign-up, send each role to its own area.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, LoginResponse::class);
    }

    /** True while MAIL_MAILER is "log" or "array": emails are written down, not sent. */
    public static function emailStaysOnThisComputer(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Our own screens, in style C (resources/views/auth).
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password', [
            'sendsRealEmail' => ! self::emailStaysOnThisComputer(),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        // The link in the "reset your password" email.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $link = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);

            // PROTOTYPE SHORTCUT, for demos on the team's own laptops only:
            // no real email is sent there, so the "Forgot your password?"
            // page shows the link itself. Never outside APP_ENV=local, and
            // never once a real mail service is set up.
            if (app()->isLocal() && self::emailStaysOnThisComputer()) {
                session()->flash('reset_link', $link);
            }

            return $link;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
