{{--
    The round "back" button at the top of a detail page.

    <x-back-link :href="route('deals.index')" label="Back to deals" />
    <x-back-link :href="route('orders.index')" label="Back to orders" on="forest" />   (on a dark header)
--}}
@props(['href', 'label' => 'Back', 'on' => 'light']) {{-- light | forest --}}

<a href="{{ $href }}"
   {{ $attributes->class([
       'flex size-11 shrink-0 items-center justify-center rounded-full border-2',
       'border-forest bg-cream text-forest hover:bg-white' => $on === 'light',
       'border-cream text-cream hover:bg-cream/10' => $on === 'forest',
   ]) }}>
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
        <path d="M12 4 6 10l6 6" />
    </svg>
    <span class="sr-only">{{ $label }}</span>
</a>
