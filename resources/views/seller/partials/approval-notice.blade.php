{{-- Shown on seller pages until an admin approves the shop. --}}
@if ($shop && ! $shop->isApproved())
    <x-card variant="marigold" class="mb-6">
        <p class="font-display text-lg font-bold">Your shop is {{ $shop->status === \App\Enums\SellerStatus::Pending ? 'waiting for approval' : str($shop->status->label())->lower() }}</p>
        <p class="mt-1 text-sm font-medium">You can prepare drafts, but listings only go live once an admin has approved your shop.</p>
    </x-card>
@endif
