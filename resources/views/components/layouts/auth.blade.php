{{--
    The layout for the log in and register screens: logo, a heading, the form.

    <x-layouts.auth title="Log in">
        <x-slot:intro>Welcome back.</x-slot:intro>
        …form…
    </x-layouts.auth>
--}}
@props(['title'])

<x-layouts.base :title="$title">
    <div class="mx-auto flex min-h-screen max-w-md flex-col px-5 pb-10">
        <header class="py-4">
            <a href="{{ route('deals.index') }}" class="inline-flex min-h-11 items-center font-display text-3xl font-bold tracking-tight">
                foodloop<span class="text-marigold">.</span>
            </a>
        </header>

        <main id="main" class="mt-6">
            <h1 class="font-display text-5xl font-bold leading-none tracking-tight">{{ $title }}</h1>
            @isset($intro)
                <p class="mt-3 text-base text-muted">{{ $intro }}</p>
            @endisset

            <div class="mt-6">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
