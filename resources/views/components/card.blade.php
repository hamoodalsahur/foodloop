{{--
    A rounded panel.

    <x-card>…</x-card>                     white (default)
    <x-card variant="forest">…</x-card>    dark panel, cream text
    <x-card variant="marigold">…</x-card>  highlight tile
    <x-card variant="outline">…</x-card>   white with a forest border
    <x-card as="section" padding="lg">…</x-card>
--}}
@props([
    'variant' => 'white', // white | forest | marigold | outline
    'padding' => 'md',    // none | md | lg
    'as' => 'div',
])

@php
    $variants = [
        'white' => 'bg-white text-forest',
        'forest' => 'bg-forest text-cream',
        'marigold' => 'bg-marigold text-forest',
        'outline' => 'border-2 border-forest bg-white text-forest',
    ];

    $paddings = ['none' => '', 'md' => 'p-5', 'lg' => 'p-6 md:p-8'];
@endphp

<{{ $as }} {{ $attributes->class(['rounded-3xl', $variants[$variant] ?? $variants['white'], $paddings[$padding] ?? $paddings['md']]) }}>
    {{ $slot }}
</{{ $as }}>
