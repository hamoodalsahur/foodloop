{{--
    The customer's impact page (mockup c04). Customers only.
    Every number is worked out from collected pickups: App\Queries\CustomerImpact.
--}}
@php
    use App\Queries\CustomerImpact;
    use App\Support\Money;
    use App\Support\PickupWindow;

    $strikesToBlock = config('foodloop.strikes_to_block');
@endphp

<x-layouts.customer title="Your impact" active="impact">
    <div class="flex items-end justify-between gap-3">
        <h1 class="font-display text-4xl font-bold tracking-tight">Your impact</h1>
        <p class="pb-1 text-right text-xs text-muted">Completed pickups only</p>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="row-span-2 flex min-h-48 flex-col justify-between rounded-3xl bg-marigold p-5">
            <p class="text-sm font-bold">Meals rescued</p>
            <p class="font-display text-8xl font-bold leading-none tracking-tighter">{{ number_format($meals) }}</p>
        </div>
        <x-card variant="forest">
            <p class="text-xs text-mist">You saved</p>
            <p class="mt-1 font-display text-2xl font-bold">{{ Money::format($savedSen) }}</p>
        </x-card>
        <x-card variant="outline">
            <p class="text-xs text-muted">No-shows</p>
            <p class="mt-1 font-display text-2xl font-bold">{{ $strikes }} / {{ $strikesToBlock }}</p>
        </x-card>
    </div>

    @if ($meals === 0)
        <x-empty-state class="mt-4"
                       title="Nothing rescued yet"
                       message="Reserve a deal and pick it up. It shows up here as soon as the seller marks it collected.">
            <x-button :href="route('deals.index')">See tonight’s deals</x-button>
        </x-empty-state>
    @else
        <x-card as="section" class="mt-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-lg font-bold">Per week</h2>
                <p class="text-xs text-muted">Last {{ $weeks->count() }} weeks</p>
            </div>
            <x-week-bars :weeks="$weeks" class="mt-10" />
        </x-card>
    @endif

    <p class="mt-4 rounded-3xl bg-forest px-5 py-4 text-sm font-medium text-cream">
        Everyone on FoodLoop this month:
        <strong class="text-marigold">{{ number_format($everyone['meals']) }} {{ Str::plural('meal', $everyone['meals']) }}</strong> rescued and
        <strong class="text-marigold">{{ number_format($everyone['donated']) }} {{ Str::plural('item', $everyone['donated']) }}</strong> sent to food banks.
    </p>

    @if ($recent->isNotEmpty())
        <h2 class="mt-6 font-display text-xl font-bold">Recent</h2>
        <ul class="mt-3 space-y-3">
            @foreach ($recent as $reservation)
                <li>
                    <a href="{{ route('orders.show', $reservation) }}"
                       class="flex min-h-20 min-w-0 items-center gap-3 rounded-3xl bg-white p-3 transition-shadow hover:shadow-md">
                        <x-food-photo :path="$reservation->listing->photo_path" alt="" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-display font-bold leading-snug">{{ $reservation->listing->title }}</p>
                            <p class="truncate text-sm text-muted">
                                {{ $reservation->listing->seller->business_name }} · {{ PickupWindow::day($reservation->collected_at) }}@if ($reservation->quantity > 1) · Qty {{ $reservation->quantity }}@endif
                            </p>
                        </div>
                        <p class="shrink-0 font-display font-bold">
                            <span class="sr-only">Saved</span>{{ Money::format(-CustomerImpact::savingSen($reservation)) }}
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-auto pb-4 pt-8 text-xs text-muted">
        “You saved” is the normal price minus what you paid. Payments are simulated and the shops and prices are demo data.
    </p>
</x-layouts.customer>
