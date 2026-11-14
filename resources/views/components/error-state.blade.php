{{--
    Shown when something went wrong loading a page or a list. Always offers "Try again".

    <x-error-state />                                              reloads the page
    <x-error-state title="Couldn't load your orders" message="Check your connection." />
    <x-error-state :retry-url="route('deals.index')" />            "Try again" goes to a URL instead

    <x-error-state page title="Couldn’t load deals." :retry-url="url()->current()">
        <x-button size="lg" variant="outline" href="…">Show my pickup code</x-button>
    </x-error-state>

    `page` is the big version for when the whole page failed (mockup c06).
--}}
@props([
    'title' => 'Something went wrong',
    'message' => 'That didn’t load. It’s probably a connection hiccup, not you.',
    'retryUrl' => null,
    'page' => false,
])

@if ($page)
    <div role="alert" {{ $attributes->class('py-10') }}>
        <div class="flex size-28 items-center justify-center rounded-full border-2 border-forest text-forest" aria-hidden="true">
            {{-- Wi-Fi with a line through it --}}
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="size-12">
                <path d="M8 19a23 23 0 0 1 32 0M14 26a14 14 0 0 1 20 0M20 33a6 6 0 0 1 8 0M10 8l28 32" />
            </svg>
        </div>
        <h2 class="mt-6 max-w-xs font-display text-5xl font-bold leading-none tracking-tight text-forest">{{ $title }}</h2>
        <p class="mt-4 max-w-sm text-base text-muted">{{ $message }}</p>
        <div class="mt-6 flex max-w-sm flex-col gap-3">
            @if ($retryUrl)
                <x-button size="lg" :href="$retryUrl">Try again</x-button>
            @else
                <x-button size="lg" x-data @click="window.location.reload()">Try again</x-button>
            @endif
            {{ $slot }}
        </div>
    </div>
@else
    <div role="alert" {{ $attributes->class('flex flex-col items-center rounded-3xl bg-danger-soft px-6 py-10 text-center') }}>
        {{-- A warning triangle --}}
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-14 text-danger" aria-hidden="true">
            <path d="M24 6 44 40H4z" />
            <path d="M24 19v10M24 34.5v.5" />
        </svg>
        <h2 class="mt-4 font-display text-xl font-bold text-forest">{{ $title }}</h2>
        <p class="mt-2 max-w-sm text-sm text-forest">{{ $message }}</p>
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            @if ($retryUrl)
                <x-button :href="$retryUrl">Try again</x-button>
            @else
                <x-button x-data @click="window.location.reload()">Try again</x-button>
            @endif
            {{ $slot }}
        </div>
    </div>
@endif
