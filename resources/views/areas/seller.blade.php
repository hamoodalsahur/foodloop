{{-- Placeholder until Stage 5 builds the real seller dashboard. Sellers only. --}}
<x-layouts.seller title="Dashboard" active="dashboard">
    @php($shop = auth()->user()->seller)

    @if ($shop && ! $shop->isApproved())
        <x-card variant="marigold" class="mb-6">
            <p class="font-display text-lg font-bold">Your shop is {{ str($shop->status->label())->lower() }}</p>
            <p class="mt-1 text-sm font-medium">You can prepare drafts, but listings only go live once an admin has approved your shop.</p>
        </x-card>
    @endif

    <x-empty-state title="Your seller dashboard will show up here"
                   message="This page is still being built. Listings, pickups and donations come in a later stage." />
</x-layouts.seller>
