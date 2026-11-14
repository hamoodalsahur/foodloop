{{-- Leftovers to offer, and everything offered to the food bank so far. --}}
@php use App\Support\PickupWindow; @endphp

<x-layouts.seller title="Donations" active="donations">
    <x-slot:subtitle>Portions given to the food bank so far: {{ $donated }}</x-slot:subtitle>

    @include('seller.partials.flash')

    <section>
        <h2 class="font-display text-2xl font-bold">Left over, not offered yet</h2>
        @forelse ($leftovers as $listing)
            <div class="mt-3 flex flex-wrap items-center gap-3 rounded-3xl bg-white p-3">
                <x-food-photo :path="$listing->photo_path" alt="" size="sm" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $listing->title }}</p>
                    <p class="text-sm text-muted">{{ $listing->leftover_count }} left · {{ PickupWindow::withDay($listing->pickup_start, $listing->pickup_end) }}</p>
                </div>
                <form method="POST" action="{{ route('seller.listings.donation', $listing) }}">
                    @csrf
                    <x-button type="submit" variant="marigold">Offer to food bank</x-button>
                </form>
            </div>
        @empty
            <x-empty-state class="mt-3" title="Nothing waiting" message="Closed listings with portions left over show up here for a week." />
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="font-display text-2xl font-bold">Offered to the food bank</h2>
        @forelse ($donations as $donation)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-4">
                <div>
                    <p class="font-bold">{{ $donation->listing->title }} × {{ $donation->quantity }}</p>
                    <p class="text-sm text-muted">To {{ $donation->partner->name }} · {{ $donation->created_at->format('j M, g:i A') }}</p>
                </div>
                <x-tag :variant="$donation->status === \App\Enums\DonationStatus::Collected ? 'forest' : 'outline'">{{ $donation->status->label() }}</x-tag>
            </div>
        @empty
            <x-empty-state class="mt-3" title="No donations yet" message="When you offer leftovers, they are listed here." />
        @endforelse
    </section>
</x-layouts.seller>
