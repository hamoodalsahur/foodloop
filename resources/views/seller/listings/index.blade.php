{{-- All of a seller's listings, grouped by where they are in their life. --}}
@php use App\Support\PickupWindow; @endphp

<x-layouts.seller title="Listings" active="listings">
    <x-slot:actions>
        @can('create', \App\Models\Listing::class)
            <x-button href="{{ route('seller.listings.create') }}">New listing</x-button>
        @endcan
    </x-slot:actions>

    @include('seller.partials.approval-notice', ['shop' => auth()->user()->seller])
    @include('seller.partials.flash')

    @if (collect($groups)->flatten()->isEmpty())
        <x-empty-state page title="No listings yet." message="Post what you have left today: it takes a minute.">
            <x-button size="lg" href="{{ route('seller.listings.create') }}">Post your first listing</x-button>
        </x-empty-state>
    @else
        <div class="space-y-8">
            @foreach ($groups as $heading => $items)
                @continue($items->isEmpty())
                <section>
                    <h2 class="font-display text-2xl font-bold">{{ $heading }} <span class="text-muted">· {{ $items->count() }}</span></h2>
                    <ul class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach ($items as $listing)
                            <li>
                                <a href="{{ route('seller.listings.edit', $listing) }}" class="flex min-h-11 items-center gap-3 rounded-3xl bg-white p-3 hover:ring-2 hover:ring-forest">
                                    <x-food-photo :path="$listing->photo_path" alt="" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="truncate font-bold">{{ $listing->title }}</span>
                                            @include('seller.partials.listing-status', ['listing' => $listing])
                                        </div>
                                        <p class="mt-0.5 text-sm text-muted">{{ PickupWindow::withDay($listing->pickup_start, $listing->pickup_end) }}</p>
                                        <p class="text-sm text-muted">{{ $listing->price_formatted }} · {{ $listing->quantity_total - $listing->quantity_available }} of {{ $listing->quantity_total }} taken</p>
                                    </div>
                                    <span class="text-sm font-semibold underline underline-offset-4">Edit</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.seller>
