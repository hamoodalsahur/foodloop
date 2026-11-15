{{--
    What is on FoodLoop right now, and what was taken down. An admin can hide any
    listing from here, reported or not. Data from Admin\ListingController.
--}}
@php
    use App\Enums\ListingStatus;
    use App\Support\PickupWindow;

    $tabs = ['now' => 'On FoodLoop now', 'hidden' => 'Taken down'];
@endphp

<x-layouts.admin title="Listings" active="listings">
    <x-slot:subtitle>Hiding a listing takes it off FoodLoop for good and cancels its waiting reservations (refunded, no strike).</x-slot:subtitle>

    @include('admin.partials.flash')

    <nav aria-label="Which listings" class="mb-6 flex gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.listings.index', $key === 'now' ? [] : ['show' => $key]) }}"
               @if ($show === $key) aria-current="page" @endif
               @class([
                   'flex min-h-11 items-center rounded-full px-4 text-sm font-semibold',
                   'bg-forest text-cream' => $show === $key,
                   'bg-white text-forest hover:bg-line' => $show !== $key,
               ])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($listings->isEmpty())
        @if ($show === 'hidden')
            <x-empty-state title="Nothing has been taken down" message="Listings you hide show up here." />
        @else
            <x-empty-state title="No listings right now" message="Listings show up here when shops put them live." />
        @endif
    @else
        <ul class="space-y-3">
            @foreach ($listings as $listing)
                <li class="flex flex-wrap items-center gap-3 rounded-3xl bg-white p-4">
                    <x-food-photo :path="$listing->photo_path" alt="" size="sm" />
                    <div class="min-w-0 flex-1 basis-56">
                        <p class="font-display font-bold leading-snug">{{ $listing->title }} · {{ $listing->seller->business_name }}</p>
                        <p class="mt-0.5 text-sm text-muted">
                            {{ $listing->price_formatted }} (was {{ $listing->original_price_formatted }})
                            · {{ $listing->quantity_available }} of {{ $listing->quantity_total }} left
                            · Pickup {{ PickupWindow::withDay($listing->pickup_start, $listing->pickup_end) }}
                        </p>
                        <p class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                            @include('seller.partials.listing-status', ['listing' => $listing])
                            <span class="text-muted">{{ $listing->halal_status->label() }}</span>
                            @if ($listing->open_reports_count > 0)
                                <a href="{{ route('admin.reports.index') }}" class="rounded-full bg-danger-soft px-2.5 py-1 font-bold text-danger">
                                    {{ $listing->open_reports_count }} open {{ Str::plural('report', $listing->open_reports_count) }}
                                </a>
                            @endif
                            @if ($listing->waiting_portions > 0)
                                <span class="text-muted">{{ $listing->waiting_portions }} reserved, waiting for pickup</span>
                            @endif
                        </p>
                    </div>
                    @if ($listing->status !== ListingStatus::Hidden)
                        <form method="POST" action="{{ route('admin.reports.hide', $listing) }}">
                            @csrf
                            <x-button type="submit" variant="outline">Hide listing<span class="sr-only">: {{ $listing->title }}</span></x-button>
                        </form>
                    @else
                        <p class="text-sm text-muted">Taken down {{ $listing->closed_at?->format('j M, g:i A') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
