{{--
    A button or a link that looks like one.

    <x-button>Save</x-button>                                   forest (default)
    <x-button variant="marigold">Check</x-button>
    <x-button variant="outline" href="/deals">Back</x-button>   renders an <a>
    <x-button size="lg" arrow>Reserve for RM8</x-button>        full-width call to action

    Always at least 44 px tall so it is easy to tap.
--}}
@props([
    'variant' => 'primary', // primary | outline | marigold
    'size' => 'md',         // md | lg (lg is full width)
    'href' => null,
    'type' => 'button',
    'arrow' => false,       // adds the round arrow badge on the right
])

@php
    $base = 'inline-flex items-center justify-center gap-3 rounded-2xl font-semibold transition-colors '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50';

    $variants = [
        'primary' => 'bg-forest text-cream hover:bg-forest/90',
        'outline' => 'border-2 border-forest bg-white text-forest hover:bg-cream',
        'marigold' => 'bg-marigold text-forest hover:bg-marigold/85',
    ];

    $sizes = [
        'md' => 'min-h-11 px-5 py-2.5 text-sm',
        'lg' => 'min-h-14 w-full px-6 py-3 text-base',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md'])
        .($arrow ? ' justify-between' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        <span>{{ $slot }}</span>
        @if ($arrow)
            <x-button.arrow :on="$variant" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        <span>{{ $slot }}</span>
        @if ($arrow)
            <x-button.arrow :on="$variant" />
        @endif
    </button>
@endif
