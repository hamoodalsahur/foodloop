{{--
    Choose a new password, reached from the link in the reset email. Fortify
    handles the form (POST /reset-password); see FortifyServiceProvider.
--}}
<x-layouts.auth title="Choose a new password">
    <x-slot:intro>Pick something you don’t use anywhere else.</x-slot:intro>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" label="Email" type="email" autocomplete="username" inputmode="email" :value="$request->query('email')" required />
        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." required autofocus />
        <x-form.input name="password_confirmation" label="New password again" type="password" autocomplete="new-password" required />

        <x-button type="submit" size="lg" arrow>Save new password</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-forest underline underline-offset-4">Back to log in</a>
    </p>
</x-layouts.auth>
