{{-- The log in screen. Fortify handles the form (POST /login); see FortifyServiceProvider. --}}
<x-layouts.auth title="Log in">
    <x-slot:intro>Good food is waiting. Log in to reserve it.</x-slot:intro>

    @if (session('status'))
        <x-notice variant="success" class="mb-4">{{ session('status') }}</x-notice>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="username" inputmode="email" required autofocus />
        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <label class="flex min-h-11 items-center gap-3 text-sm font-medium">
            <input type="checkbox" name="remember" class="size-5 accent-forest">
            Keep me logged in on this device
        </label>

        <x-button type="submit" size="lg" arrow>Log in</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        New to FoodLoop?
        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center font-semibold text-forest underline underline-offset-4">Create an account</a>
    </p>
</x-layouts.auth>
