{{--
    The seller's home page (mockup c08). Data from App\Http\Controllers\Seller\DashboardController.
--}}
@php
    use App\Support\Money;
    use App\Support\PickupWindow;
    $next = $listings->first(fn ($l) => $l->pickup_start->isFuture());
@endphp

<x-layouts.seller title="Tonight" active="dashboard">
    <x-slot:subtitle>
        {{ now()->format('l j M') }}@if ($next) · next pickup window opens at {{ PickupWindow::time($next->pickup_start) }}@endif
    </x-slot:subtitle>
    <x-slot:actions>
        @can('create', \App\Models\Listing::class)
            <x-button href="{{ route('seller.listings.create') }}">
                <span class="flex items-center gap-2">
                    <span aria-hidden="true" class="flex size-6 items-center justify-center rounded-full bg-marigold text-forest">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="size-3.5"><path d="M12 5v14M5 12h14" /></svg>
                    </span>
                    New listing
                </span>
            </x-button>
        @endcan
    </x-slot:actions>

    @include('seller.partials.approval-notice')
    @include('seller.partials.flash')

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-tile label="Waiting for pickup" :value="$stats['waiting']" variant="marigold" />
        <x-stat-tile label="Reservations today" :value="$stats['today']" />
        <x-stat-tile label="Sales this week" :value="Money::format($stats['salesWeekSen'])" />
        <x-stat-tile label="Rescued this month" :value="$stats['rescuedMonth']" variant="forest" hint="portions collected" />
    </div>

    <x-card as="section" class="mt-6" aria-labelledby="pickups-heading">
        @include('seller.partials.pickups-table', ['pickups' => $pickups])
    </x-card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card as="section">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-2xl font-bold">Live listings</h2>
                <a href="{{ route('seller.listings.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4">All listings</a>
            </div>

            @forelse ($listings as $listing)
                @php($percent = $listing->quantity_total > 0 ? (int) round($listing->taken / $listing->quantity_total * 100) : 0)
                <div class="mt-4 border-t border-line pt-4 first-of-type:border-0">
                    <div class="flex items-start gap-3">
                        <x-food-photo :path="$listing->photo_path" alt="" size="sm" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('seller.listings.edit', $listing) }}" class="font-bold underline-offset-4 hover:underline">{{ $listing->title }}</a>
                                @include('seller.partials.listing-status', ['listing' => $listing])
                            </div>
                            <p class="mt-0.5 text-sm text-muted">
                                {{ $listing->price_formatted }} <s>{{ $listing->original_price_formatted }}</s>
                                · pickup {{ PickupWindow::times($listing->pickup_start, $listing->pickup_end) }}
                            </p>
                        </div>
                        <span class="font-display text-xl font-bold">−{{ $listing->discount_percent }}%</span>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line" role="img" aria-label="{{ $listing->taken }} of {{ $listing->quantity_total }} reserved">
                        <div class="h-full bg-marigold" style="width: {{ $percent }}%"></div>
                    </div>
                    <p class="mt-1.5 text-sm font-semibold">{{ $listing->taken }} of {{ $listing->quantity_total }} reserved</p>
                </div>
            @empty
                <x-empty-state class="mt-4" title="Nothing on the deals page" message="Post what’s left today and students nearby will see it." />
            @endforelse
        </x-card>

        <x-card as="section" variant="forest">
            <h2 class="font-display text-2xl font-bold">Left over at closing?</h2>
            <p class="mt-2 text-sm text-cream/90">Offer unsold portions to our food bank partner instead of binning them.</p>

            @forelse ($leftovers as $listing)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-cream/20 pt-4">
                    <div>
                        <p class="font-bold">{{ $listing->title }}</p>
                        <p class="text-sm text-mist">{{ $listing->leftover_count }} left · closed {{ PickupWindow::day($listing->pickup_end) === 'Today' ? 'at '.PickupWindow::time($listing->pickup_end) : PickupWindow::day($listing->pickup_end) }}</p>
                    </div>
                    <form method="POST" action="{{ route('seller.listings.donation', $listing) }}">
                        @csrf
                        <x-button type="submit" variant="marigold">Offer to food bank</x-button>
                    </form>
                </div>
            @empty
                <p class="mt-4 text-sm text-mist">Nothing to offer right now. Listings with “offer leftovers” ticked are offered automatically when they close.</p>
            @endforelse

            <a href="{{ route('seller.donations.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-cream underline underline-offset-4">See donations</a>
        </x-card>
    </div>
</x-layouts.seller>
