{{--
    A live countdown clock, "00:42:10", ticking down to a moment.
    Reloads the page once when it reaches zero, so the page shows what comes next.

    <x-countdown :until="$listing->pickup_end" />
    <x-countdown :until="$listing->pickup_start" :reload="false" class="text-2xl" />
--}}
@props(['until', 'reload' => true])

@php($seconds = max(0, (int) now()->diffInSeconds($until, false)))

<span x-data="countdown({{ $seconds }}, {{ $reload ? 'true' : 'false' }})"
      x-text="clock"
      role="timer"
      {{ $attributes->class('font-display font-bold tabular-nums') }}>{{ \App\Support\TimeLeft::clock($until) }}</span>
