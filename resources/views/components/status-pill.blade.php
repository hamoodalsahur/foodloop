{{--
    A reservation's status as a coloured pill.
    Pass the enum (or its string value): reserved, collected, no_show, cancelled.

    <x-status-pill :status="$reservation->status" />
    <x-status-pill status="no_show" />

    The colour is never the only signal: the pill always carries the word.
--}}
@props(['status'])

@php
    $status = $status instanceof \App\Enums\ReservationStatus
        ? $status
        : \App\Enums\ReservationStatus::from($status);

    $styles = [
        'reserved' => 'bg-marigold-soft text-forest',
        'collected' => 'bg-forest text-cream',
        'no_show' => 'bg-danger-soft text-danger',
        'cancelled' => 'bg-line text-muted',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold whitespace-nowrap', $styles[$status->value]]) }}>
    {{ $status->label() }}
</span>
