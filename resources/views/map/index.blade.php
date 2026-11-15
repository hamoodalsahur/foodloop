{{--
    The map page. A street map around campus (resources/js/map.js) with a numbered pin per shop
    that has deals right now; the numbers match the list underneath. Under it, a simplified
    drawing (rings of distance around campus) that shows instead when the street map can't
    load: no internet, or no JavaScript. Data from App\Http\Controllers\MapController.
--}}
@php
    use App\Support\Distance;

    // The drawing is a 320 × 320 square with "you" in the middle; `scale` turns km into drawing units.
    $size = 320;
    $centre = $size / 2;
    $scale = ($centre - 26) / $radiusKm;
@endphp

<x-layouts.customer title="Map" active="map" wide>
    <x-slot:notice>
        @if ($dealCount > 0)
            {{ $dealCount }} {{ Str::plural('deal', $dealCount) }} at {{ $shops->count() }} {{ Str::plural('shop', $shops->count()) }} right now
        @else
            Quiet right now · sellers usually post near closing time
        @endif
    </x-slot:notice>

    <h1 class="font-display text-4xl font-bold tracking-tight">Map</h1>

    @if ($shops->isEmpty())
        <x-empty-state class="mt-6" title="No shops have deals right now" message="When a seller posts food, the shop shows up here.">
            <x-button :href="route('deals.index')">Back to the deals</x-button>
        </x-empty-state>
    @else
        {{-- data-state: "drawing" (the default, also without JavaScript) or "street" once map.js runs. --}}
        <div class="group mt-4" data-state="drawing" data-map="{{ json_encode($mapData) }}">
        <x-card padding="none" class="overflow-hidden">
            <div data-street-map class="hidden h-[26rem] w-full group-data-[state=street]:block lg:h-[32rem]"
                 role="region" aria-label="Street map of the shops around {{ $mapData['origin']['label'] }}"></div>
            <svg viewBox="0 0 {{ $size }} {{ $size }}" role="img" class="block w-full group-data-[state=street]:hidden"
                 aria-label="Simplified map: {{ $onMap->count() }} {{ Str::plural('shop', $onMap->count()) }} within {{ $radiusKm }} km of you. The list below has the same shops with their distances.">
                {{-- Distance rings --}}
                @foreach ($rings as $ring)
                    <circle cx="{{ $centre }}" cy="{{ $centre }}" r="{{ round($ring * $scale, 1) }}"
                            class="fill-none stroke-line" stroke-width="1.5" @if (! $loop->last) stroke-dasharray="4 5" @endif />
                    <text x="{{ $centre }}" y="{{ round($centre - $ring * $scale - 5, 1) }}" text-anchor="middle"
                          class="fill-muted font-sans text-[10px] font-semibold">{{ $ring }} km</text>
                @endforeach

                {{-- North --}}
                <text x="{{ $size - 16 }}" y="22" text-anchor="middle" class="fill-muted font-display text-[13px] font-bold">N</text>
                <path d="M{{ $size - 16 }} 26v12m-4-8 4-4 4 4" class="fill-none stroke-muted" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />

                {{-- You are here --}}
                <circle cx="{{ $centre }}" cy="{{ $centre }}" r="11" class="fill-marigold" opacity="0.35" />
                <circle cx="{{ $centre }}" cy="{{ $centre }}" r="5.5" class="fill-marigold stroke-forest" stroke-width="2" />
                <text x="{{ $centre }}" y="{{ $centre + 24 }}" text-anchor="middle" class="fill-forest font-sans text-[10px] font-bold">You</text>

                {{-- One numbered pin per shop; tapping it jumps to the shop in the list. --}}
                @foreach ($onMap as $shop)
                    @php
                        $x = round($centre + $shop['offset']['east'] * $scale, 1);
                        $y = round($centre - $shop['offset']['north'] * $scale, 1);
                    @endphp
                    <a href="#shop-{{ $shop['number'] }}" aria-label="{{ $shop['number'] }}: {{ $shop['seller']->business_name }}, {{ Distance::label($shop['distanceKm']) }}">
                        {{-- A see-through circle makes the pin easy to tap. --}}
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="22" class="fill-forest" opacity="0" />
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="14" class="fill-forest stroke-white" stroke-width="2.5" />
                        <text x="{{ $x }}" y="{{ $y + 4.5 }}" text-anchor="middle" class="fill-cream font-display text-[13px] font-bold">{{ $shop['number'] }}</text>
                    </a>
                @endforeach
            </svg>
        </x-card>

        <p class="mt-2 text-xs text-muted">
            <span data-offline-note hidden>Couldn’t load the street map (no internet?), so this is a simplified drawing. </span>
            Distances are from {{ $mapData['origin']['label'] }}. The shops are real places nearby, but they haven’t joined FoodLoop: their deals are demo data.
        </p>
        </div>

        <ol class="mt-5 space-y-6">
            @foreach ($shops as $shop)
                <li id="shop-{{ $shop['number'] }}" class="scroll-mt-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-forest font-display text-sm font-bold text-cream" aria-hidden="true">{{ $shop['number'] }}</span>
                        <div class="min-w-0">
                            <h2 class="truncate font-display text-lg font-bold leading-tight">{{ $shop['seller']->business_name }}</h2>
                            <p class="text-sm text-muted">
                                @if ($shop['distanceKm'] !== null)
                                    {{ Distance::label($shop['distanceKm']) }} away ·
                                @endif
                                {{ $shop['listings']->count() }} {{ Str::plural('deal', $shop['listings']->count()) }}
                            </p>
                        </div>
                        @if ($shop['seller']->lat !== null && $shop['seller']->lng !== null)
                            <a href="{{ \App\Http\Controllers\MapController::directions($shop['seller']->lat, $shop['seller']->lng) }}" target="_blank" rel="noopener"
                               class="ml-auto inline-flex min-h-11 shrink-0 items-center rounded-xl border-2 border-forest px-3 text-sm font-bold">
                                Directions
                            </a>
                        @endif
                    </div>
                    <ul class="mt-3 space-y-3">
                        @foreach ($shop['listings'] as $listing)
                            <li><x-deal-row :listing="$listing" :href="route('listings.show', $listing)" /></li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ol>
    @endif
</x-layouts.customer>
