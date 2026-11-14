{{-- The sign-up screen for customers. Fortify handles the form (POST /register); new accounts are always customers. --}}
<x-layouts.auth title="Create your account">
    <x-slot:intro>Rescue surplus food from campus sellers at a discount.</x-slot:intro>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf

        <x-form.input name="name" label="Your name" autocomplete="name" required autofocus />
        <x-form.input name="email" label="Email" type="email" autocomplete="username" inputmode="email" required />
        <x-form.input name="password" label="Password" type="password" autocomplete="new-password" required hint="At least 8 characters." />
        <x-form.input name="password_confirmation" label="Password again" type="password" autocomplete="new-password" required />

        <x-button type="submit" size="lg" arrow>Create account</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-forest underline underline-offset-4">Log in</a>
    </p>
</x-layouts.auth>
