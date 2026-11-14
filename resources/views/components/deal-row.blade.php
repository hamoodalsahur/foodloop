{{--
    One deal in a list: picture, time left, title, shop, price. The whole row is a link.

    <x-deal-row :listing="$listing" :href="route('listings.show', $listing)" distance="0.4 km" />

    The listing needs its seller loaded (Listing::with('seller')) to avoid a query per row.
    "X min left" counts down to the end of the pickup window and turns marigold when it is close.
--}}
@props(['listing', 'href' => '#', 'distance' => null])

@php
    $timeLeft = \App\Support\TimeLeft::class;
    $soldOut = $listing->quantity_available < 1;
@endphp

<a href="{{ $href }}" {{ $attributes->class('flex min-h-24 min-w-0 items-center gap-3 rounded-3xl bg-white p-3 text-forest transition-shadow hover:shadow-md') }}>
    <x-food-photo :path="$listing->photo_path" alt="" class="max-sm:size-[4.5rem]" />

    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-display text-base font-bold leading-tight">
            @if ($soldOut)
                <x-tag>Sold out</x-tag>
            @else
                <span @class(['rounded-md bg-marigold px-1.5' => $timeLeft::isUrgent($listing->pickup_end)])>
                    {{ $timeLeft::label($listing->pickup_end) }}
                </span>
            @endif
        </p>
        <p class="truncate font-display text-base font-bold leading-snug">{{ $listing->title }}</p>
        {{-- The shop name shortens if it must; the distance always stays readable. --}}
        <p class="flex text-[0.8125rem] text-muted sm:text-sm">
            <span class="truncate">{{ $listing->seller->business_name }}</span>
            @if ($distance)
                <span class="shrink-0 whitespace-pre"> · {{ $distance }}</span>
            @endif
        </p>
    </div>

    <x-price-box :price-sen="$listing->price_sen" :original-sen="$listing->original_price_sen" />
</a>
