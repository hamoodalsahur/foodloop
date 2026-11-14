{{--
    One big number with a label, for dashboards and the impact page.

    <x-stat-tile label="Waiting for pickup" value="3" variant="marigold" />
    <x-stat-tile label="Sales this week" :value="Money::format($sen)" />     outline (default)
    <x-stat-tile label="Rescued this month" value="64" variant="forest" />
    <x-stat-tile label="Meals rescued" value="14" hint="since you joined" />
--}}
@props([
    'label',
    'value',
    'variant' => 'outline', // outline | marigold | forest
    'hint' => null,
])

@php
    $variants = [
        'outline' => 'border-2 border-forest bg-white text-forest',
        'marigold' => 'bg-marigold text-forest',
        'forest' => 'bg-forest text-cream',
    ];
    $labelColour = ['outline' => 'text-muted', 'marigold' => 'text-forest font-semibold', 'forest' => 'text-mist'][$variant] ?? 'text-muted';
@endphp

<div {{ $attributes->class(['rounded-3xl p-4', $variants[$variant] ?? $variants['outline']]) }}>
    <p class="text-sm {{ $labelColour }}">{{ $label }}</p>
    <p class="mt-1 font-display text-4xl font-bold leading-none tracking-tight md:text-5xl">{{ $value }}</p>
    @if ($hint)
        <p class="mt-2 text-xs {{ $labelColour }}">{{ $hint }}</p>
    @endif
</div>
