{{--
    A short message in a soft box: a rule to know, a confirmation, or something that went wrong.

    <x-notice>Free cancellation until <strong>6:30 PM</strong>.</x-notice>     info (soft marigold, default)
    <x-notice variant="danger">Sold out. Someone just took the last one.</x-notice>
    <x-notice variant="success">Reservation cancelled.</x-notice>

    Danger notices are announced to screen readers straight away; success ones politely.
--}}
@props(['variant' => 'info']) {{-- info | danger | success --}}

@php
    $variants = [
        'info' => 'bg-marigold-soft text-forest',
        'danger' => 'bg-danger-soft text-forest border-l-4 border-danger',
        'success' => 'bg-forest text-cream',
    ];
    $role = ['danger' => 'alert', 'success' => 'status'][$variant] ?? null;
@endphp

<div @if ($role) role="{{ $role }}" @endif
     {{ $attributes->class(['rounded-2xl px-4 py-3 text-sm', $variants[$variant] ?? $variants['info']]) }}>
    {{ $slot }}
</div>
