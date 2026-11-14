{{--
    The dark price block on a deal: what you pay, and the crossed-out original.
    Prices are whole sen (RM8 = 800); App\Support\Money writes the text.

    <x-price-box :price-sen="800" :original-sen="2000" />
    <x-price-box :price-sen="$listing->price_sen" :original-sen="$listing->original_price_sen" />
--}}
@props(['priceSen', 'originalSen' => null])

@php($money = \App\Support\Money::class)

<div {{ $attributes->class('flex min-w-[4.25rem] shrink-0 flex-col items-center justify-center rounded-2xl bg-forest px-2.5 py-2 text-cream') }}>
    <span class="font-display text-lg font-bold leading-tight">{{ $money::format($priceSen) }}</span>
    @if ($originalSen !== null && $originalSen > $priceSen)
        <span class="text-xs text-mist">
            <span class="sr-only">instead of</span>
            <s>{{ $money::format($originalSen) }}</s>
        </span>
    @endif
</div>
