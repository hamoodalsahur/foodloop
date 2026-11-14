{{--
    The customer's own reservations: the ones waiting for pickup first, then earlier ones.
    Data comes from App\Http\Controllers\OrderController@index.
--}}
@php
    use App\Support\PickupWindow;
@endphp

<x-layouts.customer title="Orders" active="orders">
    <h1 class="font-display text-4xl font-bold tracking-tight">Orders</h1>

    @if ($waiting->isEmpty() && $past->isEmpty())
        <x-empty-state class="mt-6"
                       title="No orders yet"
                       message="When you reserve a deal, your pickup code shows up here.">
            <x-button :href="route('deals.index')">See tonight’s deals</x-button>
        </x-empty-state>
    @endif

    @if ($waiting->isNotEmpty())
        <h2 class="mt-6 font-display text-xl font-bold">Waiting for pickup</h2>
        <ul class="mt-3 space-y-3">
            @foreach ($waiting as $reservation)
                <li>
                    <a href="{{ route('orders.show', $reservation) }}"
                       class="flex min-h-24 min-w-0 items-center gap-3 rounded-3xl bg-white p-3 transition-shadow hover:shadow-md">
                        <x-food-photo :path="$reservation->listing->photo_path" alt="" class="max-sm:size-[4.5rem]" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-display font-bold leading-snug">{{ $reservation->listing->title }}</p>
                            <p class="truncate text-sm text-muted">{{ $reservation->listing->seller->business_name }} · Qty {{ $reservation->quantity }}</p>
                            <p class="text-sm font-semibold">{{ PickupWindow::withDay($reservation->listing->pickup_start, $reservation->listing->pickup_end) }}</p>
                        </div>
                        <div class="shrink-0 rounded-2xl bg-marigold px-3 py-2 text-center">
                            <p class="text-[0.625rem] font-bold uppercase tracking-widest">Code</p>
                            <p class="font-display text-xl font-bold leading-tight tracking-wider">{{ $reservation->pickup_code }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($past->isNotEmpty())
        <h2 class="mt-8 font-display text-xl font-bold">Earlier</h2>
        <ul class="mt-3 space-y-3">
            @foreach ($past as $reservation)
                <li>
                    <a href="{{ route('orders.show', $reservation) }}"
                       class="flex min-h-20 min-w-0 items-center gap-3 rounded-3xl bg-white p-3 transition-shadow hover:shadow-md">
                        <x-food-photo :path="$reservation->listing->photo_path" alt="" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-display font-bold leading-snug">{{ $reservation->listing->title }}</p>
                            <p class="truncate text-sm text-muted">{{ $reservation->listing->seller->business_name }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ PickupWindow::day($reservation->listing->pickup_start) }} · {{ $reservation->total_formatted }} · Qty {{ $reservation->quantity }}
                            </p>
                        </div>
                        <x-status-pill :status="$reservation->status" class="shrink-0" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($waiting->isEmpty() && $past->isNotEmpty())
        <div class="mt-6">
            <x-button :href="route('deals.index')">See tonight’s deals</x-button>
        </div>
    @endif
</x-layouts.customer>
