{{--
    A small bar chart of one number per week (the impact page, mockup c04).

    <x-week-bars :weeks="$weeks" unit="meal" />

    `weeks` is a list of ['label' => 'W1', 'dates' => '9–15 Nov', 'meals' => 4, 'current' => false], oldest first.
    The current week is marigold AND says "This week", so colour is not the only signal.
    Every bar carries its number; hovering or tabbing to a bar shows the dates; screen
    readers get the same numbers as a table.
--}}
@props(['weeks', 'unit' => 'meal'])

@php
    $max = max(1, $weeks->max('meals'));
    $plotRem = 7; // height of the tallest bar
@endphp

<div {{ $attributes }}>
    <div class="flex items-end gap-2" aria-hidden="true">
        @foreach ($weeks as $week)
            <div class="group relative flex min-w-0 flex-1 flex-col items-center" tabindex="0">
                {{-- Dates, shown on hover and keyboard focus --}}
                <span class="pointer-events-none absolute -top-9 z-10 hidden whitespace-nowrap rounded-lg bg-forest px-2 py-1 text-xs font-medium text-cream group-hover:block group-focus:block">
                    {{ $week['dates'] }} · {{ $week['meals'] }} {{ Str::plural($unit, $week['meals']) }}
                </span>

                <span class="font-display text-sm font-bold text-forest">{{ $week['meals'] }}</span>
                <span @class([
                          'mt-1 block w-full rounded-t-md',
                          'bg-marigold' => $week['current'],
                          'bg-forest' => ! $week['current'],
                      ])
                      style="height: {{ $week['meals'] > 0 ? max(0.5, round($week['meals'] / $max * $plotRem, 2)) : 0.125 }}rem"></span>
            </div>
        @endforeach
    </div>

    {{-- The baseline the bars stand on --}}
    <div class="h-0.5 bg-forest" aria-hidden="true"></div>

    <div class="mt-1.5 flex gap-2 text-center text-xs text-muted" aria-hidden="true">
        @foreach ($weeks as $week)
            <span @class(['min-w-0 flex-1', 'font-bold text-forest' => $week['current']])>
                {{ $week['label'] }}@if ($week['current'])<span class="block text-[0.625rem] leading-tight">This week</span>@endif
            </span>
        @endforeach
    </div>

    <table class="sr-only">
        <caption>{{ Str::plural(ucfirst($unit)) }} per week, last {{ $weeks->count() }} weeks</caption>
        <thead>
            <tr><th scope="col">Week</th><th scope="col">{{ Str::plural(ucfirst($unit)) }}</th></tr>
        </thead>
        <tbody>
            @foreach ($weeks as $week)
                <tr>
                    <th scope="row">{{ $week['dates'] }}@if ($week['current']) (this week)@endif</th>
                    <td>{{ $week['meals'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
