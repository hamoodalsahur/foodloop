{{--
    Shown when a list has nothing in it. Say what is empty, why, and what to do next.

    <x-empty-state title="No deals nearby right now" message="Sellers usually post leftovers after 5 PM.">
        <x-button variant="outline" href="/">Show all areas</x-button>
    </x-empty-state>
--}}
@props(['title', 'message' => null])

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
