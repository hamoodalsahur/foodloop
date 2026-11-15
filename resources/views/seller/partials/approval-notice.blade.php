{{-- Shown on seller pages until an admin approves the shop. --}}
@if ($shop && ! $shop->isApproved())
    <x-card variant="marigold" class="mb-6">
        <p class="font-display text-lg font-bold">Your shop is {{ $shop->status === \App\Enums\SellerStatus::Pending ? 'waiting for approval' : str($shop->status->label())->lower() }}</p>
        <p class="mt-1 text-sm font-medium">You can prepare drafts, but listings only go live once an admin has approved your shop.</p>
        @if (filled($shop->info_request))
            {{-- The admin's "Ask for info". Saving the shop profile answers it. --}}
            <div class="mt-3 rounded-2xl bg-white p-3 text-sm">
                <p class="font-bold">FoodLoop asked {{ $shop->info_requested_at->diffForHumans() }}:</p>
                <p class="mt-1">“{{ $shop->info_request }}”</p>
                @unless (request()->routeIs('seller.profile.edit'))
                    <x-button :href="route('seller.profile.edit')" class="mt-3">Update shop profile</x-button>
                @else
                    <p class="mt-2 text-muted">Update your details below and save. The admin is told straight away.</p>
                @endunless
            </div>
        @endif
    </x-card>
@endif
