{{--
    Shown when a list has nothing in it. Say what is empty, why, and what to do next.

    <x-empty-state title="No pickups yet today" message="They will show up here as customers reserve." />

    <x-empty-state page title="All gone nearby." message="Everything within 2 km has been rescued.">
        <x-button size="lg" href="…">Search within 5 km</x-button>
    </x-empty-state>

    `page` is the big version for when the whole page is empty (mockup c05).
    Without it you get a compact dashed box for inside a card or a section.
--}}
@props(['title', 'message' => null, 'page' => false])

@if ($page)
    <div {{ $attributes->class('py-10') }}>
        <div class="flex size-28 items-center justify-center rounded-full bg-marigold text-forest" aria-hidden="true">
            {{-- A shopping bag --}}
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="size-12">
                <path d="M11 17h26l-2 22H13zM18 17v-3a6 6 0 0 1 12 0v3M20 27h8" />
            </svg>
        </div>
        <h2 class="mt-6 max-w-xs font-display text-5xl font-bold leading-none tracking-tight text-forest">{{ $title }}</h2>
        @if ($message)
            <p class="mt-4 max-w-sm text-base text-muted">{{ $message }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-6 flex max-w-sm flex-col gap-3">{{ $slot }}</div>
        @endif
    </div>
@else
    <div {{ $attributes->class('flex flex-col items-center rounded-3xl border-2 border-dashed border-line px-6 py-10 text-center') }}>
        {{-- An empty plate --}}
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" class="size-14 text-muted" aria-hidden="true">
            <circle cx="24" cy="24" r="19" />
            <circle cx="24" cy="24" r="11" stroke-dasharray="3 4" />
        </svg>
        <h2 class="mt-4 font-display text-xl font-bold text-forest">{{ $title }}</h2>
        @if ($message)
            <p class="mt-2 max-w-sm text-sm text-muted">{{ $message }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-5 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
        @endif
    </div>
@endif
