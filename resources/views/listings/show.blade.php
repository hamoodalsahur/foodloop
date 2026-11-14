{{--
    One listing (mockups c02 and c07). Everyone can open it.
    $state is 'open' (can be reserved), 'sold_out', 'closed' (pickup has started, no new reservations) or 'ended'.
    Data comes from App\Http\Controllers\ListingController.
--}}
@php
    use App\Enums\ListingType;
    use App\Enums\PaymentMethod;
    use App\Support\Distance;
    use App\Support\Money;
    use App\Support\PickupWindow;

    $seller = $listing->seller;
    $user = auth()->user();
    $isBundle = $listing->type === ListingType::Bundle;

    // How far the countdown bar has run: from when the listing went live to when reservations close.
    $startedAt = $listing->published_at ?? $listing->created_at;
    $total = max(1, $startedAt->diffInSeconds($listing->reservationsCloseAt()));
    $elapsedPercent = (int) round(min(1, max(0, $startedAt->diffInSeconds(now()) / $total)) * 100);
@endphp

<x-layouts.customer :title="$listing->title" :header="false" :nav="false" flush>
    {{-- Picture, back button, deal type --}}
    <div class="relative overflow-hidden md:mx-5 md:mt-5 md:rounded-3xl">
        <x-food-photo :path="$listing->photo_path" alt="" size="hero"
                      :class="$state === 'open' ? '' : 'opacity-40 grayscale'" />

        <div class="absolute inset-x-5 top-5 flex items-start justify-between gap-3">
            <x-back-link :href="route('deals.index')" label="Back to deals" />
            @if ($listing->type !== ListingType::Regular)
                <x-tag class="px-3 py-1 text-xs">{{ $listing->type->label() }}</x-tag>
            @endif
        </div>

        @if ($state === 'open' && $isBundle)
            <p class="absolute bottom-3 right-5 rounded-full bg-white px-3 py-1 text-xs font-semibold">Example of a typical bundle</p>
        @endif

        @if ($state !== 'open')
            <div class="absolute inset-x-5 bottom-3">
                <p class="text-sm font-bold">
                    @if ($state === 'sold_out')
                        All {{ $listing->quantity_total }} rescued
                    @elseif ($state === 'closed')
                        Pickup started at {{ PickupWindow::time($listing->pickup_start) }} · no new reservations
                    @else
                        Pickup window ended {{ PickupWindow::day($listing->pickup_end) === 'Today' ? 'at '.PickupWindow::time($listing->pickup_end) : PickupWindow::day($listing->pickup_end) }}
                    @endif
                </p>
                <p class="font-display text-6xl font-bold leading-none tracking-tight">{{ match ($state) { 'sold_out' => 'Sold out', 'closed' => 'Closed', default => 'Ended' } }}</p>
            </div>
        @endif
    </div>

    @if ($state === 'open')
        {{-- The discount --}}
        <div class="flex items-end justify-between gap-4 bg-marigold px-5 py-3 md:mx-5">
            <p class="font-display text-7xl font-bold leading-none tracking-tighter">
                <span class="sr-only">{{ $listing->discount_percent }}% off</span>
                <span aria-hidden="true">−{{ $listing->discount_percent }}%</span>
            </p>
            <p class="pb-1 text-right text-sm font-bold leading-snug">
                You pay {{ $listing->price_formatted }}<br>instead of {{ $listing->original_price_formatted }}
            </p>
        </div>

        {{-- The countdown --}}
        <div class="bg-forest px-5 py-4 text-cream md:mx-5 md:rounded-b-3xl">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-mist">Reservations close in</p>
                <x-countdown :until="$listing->reservationsCloseAt()" class="text-2xl" />
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-cream/15" aria-hidden="true">
                <div class="h-full rounded-full bg-marigold" style="width: {{ $elapsedPercent }}%"></div>
            </div>
        </div>
    @endif

    <div class="flex flex-1 flex-col px-5 pb-6 pt-5">
        <h1 class="font-display text-3xl font-bold leading-tight tracking-tight">{{ $listing->title }}</h1>
        <p class="mt-1 text-sm text-muted">
            {{ $seller->business_name }}@if ($listing->distanceKm !== null) · {{ Distance::label($listing->distanceKm) }}@endif
            @if ($seller->isApproved()) · verified seller @endif
        </p>

        @if ($state === 'open')
            <x-spec-grid class="mt-4" :items="[
                'Pickup' => $listing->pickup_start->isToday() ? PickupWindow::times($listing->pickup_start, $listing->pickup_end) : PickupWindow::withDay($listing->pickup_start, $listing->pickup_end),
                'Left' => $listing->quantity_available.' of '.$listing->quantity_total,
                'Halal' => $listing->halal_status->shortLabel(),
                'Contains' => $listing->allergens ? str(implode(', ', $listing->allergens))->ucfirst() : 'Not stated',
            ]" />

            @if ($listing->description)
                <p class="mt-4 text-base leading-relaxed">{{ $listing->description }}</p>
            @endif

            <p class="mt-3 text-xs text-muted">
                Halal status is declared by the seller. “Certified” means an admin has checked the seller’s halal certificate.
            </p>
        @endif

        @error('reservation')
            <x-notice variant="danger" class="mt-4">{{ $message }}</x-notice>
        @enderror

        @if ($state === 'open')
            {{-- Reserve: who is looking decides what is shown. --}}
            <div class="mt-auto pt-6">
                @guest
                    <x-button size="lg" arrow :href="route('login')">Log in to reserve</x-button>
                    <p class="mt-3 text-center text-sm text-muted">
                        New here? <a href="{{ route('register') }}" class="font-semibold text-forest underline underline-offset-4">Create an account</a>
                    </p>
                @else
                    @if (! $canReserve)
                        <x-notice>You’re signed in with {{ $user->isAdmin() ? 'an admin' : 'a seller' }} account. Only customer accounts can reserve food.</x-notice>
                    @elseif ($user->isBlocked())
                        <x-notice variant="danger">
                            You can’t reserve until <strong>{{ $user->blocked_until->format('D j M, g:i A') }}</strong> because of missed pickups.
                        </x-notice>
                        <x-button size="lg" disabled class="mt-3">Reserving is paused</x-button>
                    @elseif ($maxQuantity < 1)
                        <x-notice>
                            You already have {{ $held }} of this listing, the most one person can take.
                            <a href="{{ route('orders.index') }}" class="font-semibold underline underline-offset-4">See your orders</a>
                        </x-notice>
                    @else
                        <form method="POST" action="{{ route('reservations.store', $listing) }}"
                              x-data="quantityPicker({ max: {{ $maxQuantity }}, priceSen: {{ $listing->price_sen }}, initial: {{ (int) old('quantity', 1) }} })">
                            @csrf

                            <div class="flex items-center justify-between gap-4">
                                <label for="quantity" class="font-display text-lg font-bold">How many?</label>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="fewer()" :disabled="quantity <= 1"
                                            class="flex size-11 items-center justify-center rounded-full border-2 border-forest bg-white text-xl font-bold disabled:opacity-40">
                                        <span aria-hidden="true">−</span><span class="sr-only">One fewer</span>
                                    </button>
                                    <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $maxQuantity }}"
                                           value="{{ (int) old('quantity', 1) }}" x-model.number="quantity"
                                           class="h-11 w-14 rounded-xl border-2 border-forest bg-white text-center font-display text-lg font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                    <button type="button" @click="more()" :disabled="quantity >= max"
                                            class="flex size-11 items-center justify-center rounded-full border-2 border-forest bg-white text-xl font-bold disabled:opacity-40">
                                        <span aria-hidden="true">+</span><span class="sr-only">One more</span>
                                    </button>
                                </div>
                            </div>
                            <p class="mt-1 text-right text-xs text-muted">
                                Up to {{ $maxQuantity }} for you{{ $held ? ' (you already have '.$held.')' : '' }}
                            </p>
                            @error('quantity')
                                <p class="mt-1 text-right text-sm font-medium text-danger">{{ $message }}</p>
                            @enderror

                            <fieldset class="mt-4">
                                <legend class="font-display text-lg font-bold">How will you pay?</legend>
                                <div class="mt-2 grid grid-cols-2 gap-3">
                                    @foreach ([
                                        [PaymentMethod::PayNow, 'Pay now', 'Simulated. No real money moves.'],
                                        [PaymentMethod::PayAtPickup, 'Pay at pickup', 'Pay the seller when you collect.'],
                                    ] as [$method, $label, $hint])
                                        <label class="flex cursor-pointer flex-col rounded-2xl border-2 border-line bg-white p-3 has-checked:border-forest has-checked:bg-marigold-soft has-focus-visible:outline-3 has-focus-visible:outline-marigold">
                                            <span class="flex items-center gap-2 font-bold">
                                                <input type="radio" name="payment_method" value="{{ $method->value }}" required
                                                       class="size-4 accent-forest"
                                                       @checked(old('payment_method', PaymentMethod::PayNow->value) === $method->value)>
                                                {{ $label }}
                                            </span>
                                            <span class="mt-1 text-xs text-muted">{{ $hint }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('payment_method')
                                    <p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <x-button type="submit" size="lg" arrow class="mt-5">
                                Reserve for <span x-text="total">{{ Money::format($listing->price_sen * (int) old('quantity', 1)) }}</span>
                            </x-button>
                        </form>
                    @endif
                @endguest
            </div>
        @else
            {{-- Sold out or ended: point to what can still be rescued. --}}
            <h2 class="mt-6 font-display text-xl font-bold">Still available nearby</h2>

            @if ($alternatives->isEmpty())
                <p class="mt-2 text-sm text-muted">Nothing else right now. Sellers usually post near closing time.</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($alternatives as $other)
                        <li>
                            <x-deal-row :listing="$other" :href="route('listings.show', $other)" :distance="Distance::label($other->distanceKm)" />
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-auto space-y-3 pt-6">
                <x-button size="lg" variant="outline" :href="route('deals.index')">See all deals</x-button>
                {{-- Looks like the reserve button, but there is nothing to press. --}}
                <p class="flex min-h-14 items-center justify-center rounded-2xl bg-line font-semibold text-muted">{{ match ($state) { 'sold_out' => 'Sold out', 'closed' => 'Closed', default => 'Ended' } }}</p>
            </div>
        @endif
    </div>
</x-layouts.customer>
