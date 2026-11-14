{{--
    Shown when something went wrong loading a page or a list. Always offers "Try again".

    <x-error-state />                                              reloads the page
    <x-error-state title="Couldn't load your orders" message="Check your connection." />
    <x-error-state :retry-url="route('home')" />                   "Try again" goes to a URL instead
--}}
@props([
    'title' => 'Something went wrong',
    'message' => 'That didn’t load. It’s probably a connection hiccup, not you.',
    'retryUrl' => null,
])

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
