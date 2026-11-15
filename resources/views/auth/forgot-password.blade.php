{{--
    "Forgot your password?": ask for a reset link. Fortify handles the form
    (POST /forgot-password); see FortifyServiceProvider.
    `sendsRealEmail` is false while MAIL_MAILER is "log": the prototype says so.
--}}
<x-layouts.auth title="Forgot your password?">
    <x-slot:intro>Enter the email you signed up with and we’ll send you a link to choose a new one.</x-slot:intro>

    @if (session('status'))
        <x-notice variant="success" class="mb-4">{{ session('status') }}</x-notice>
    @endif

    @if (session('reset_link'))
        {{-- Only on the team's own computers (APP_ENV=local) while no real email is sent. --}}
        <x-notice class="mb-4">
            <p class="font-bold">Prototype only</p>
            <p class="mt-1">No real email is sent on this computer, so here is the link that the email would contain.</p>
            <x-button :href="session('reset_link')" class="mt-3">Open the reset link</x-button>
        </x-notice>
    @elseif (! $sendsRealEmail)
        <x-notice class="mb-4">
            <p class="font-bold">Prototype</p>
            <p class="mt-1">FoodLoop doesn’t send real email yet. The reset email is written to <code>storage/logs/laravel.log</code> on the computer that runs FoodLoop.</p>
        </x-notice>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="username" inputmode="email" required autofocus />

        <x-button type="submit" size="lg" arrow>Send reset link</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        Remembered it?
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-forest underline underline-offset-4">Back to log in</a>
    </p>
</x-layouts.auth>
