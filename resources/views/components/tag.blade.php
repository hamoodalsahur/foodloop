{{--
    A small uppercase label: deal type, SOLD OUT, ON.

    <x-tag>Surprise Bundle</x-tag>            forest (default)
    <x-tag variant="marigold">On</x-tag>
    <x-tag variant="outline">Flash Deal</x-tag>
--}}
@props(['variant' => 'forest']) {{-- forest | marigold | outline --}}

@php
    $variants = [
        'forest' => 'bg-forest text-cream',
        'marigold' => 'bg-marigold text-forest',
        'outline' => 'border border-forest text-forest',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-[0.6875rem] font-bold uppercase tracking-wider whitespace-nowrap', $variants[$variant] ?? $variants['forest']]) }}>
    {{ $slot }}
</span>
