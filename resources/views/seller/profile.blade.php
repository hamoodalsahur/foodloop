{{-- The seller's shop details. Halal status and approval are set with an admin, not here. --}}
<x-layouts.seller title="Shop profile" active="profile">
    @include('seller.partials.approval-notice')
    @include('seller.partials.flash')

    <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
        <form method="POST" action="{{ route('seller.profile.update') }}" class="space-y-4 rounded-3xl bg-white p-5 md:p-6">
            @csrf
            @method('PUT')
            <x-form.input name="business_name" label="Shop name" :value="$shop->business_name" required maxlength="80" />
            <x-form.input name="ssm_no" label="SSM registration number" :value="$shop->ssm_no" maxlength="50" />
            <x-form.input name="phone" label="Phone" type="tel" :value="$shop->phone" maxlength="30" />
            <x-form.input name="address" label="Address" :value="$shop->address" maxlength="255" />
            <div class="flex justify-end">
                <x-button type="submit">Save details</x-button>
            </div>
        </form>

        <x-card variant="outline" class="space-y-2 text-sm">
            <h2 class="font-display text-lg font-bold">Checked by FoodLoop</h2>
            <p><span class="text-muted">Status:</span> <strong>{{ $shop->isApproved() ? 'Verified' : $shop->status->label() }}</strong></p>
            <p><span class="text-muted">Halal:</span> <strong>{{ $shop->halal_claim->label() }}</strong>{{ $shop->halal_cert_path ? ' · certificate on file' : '' }}</p>
            <p class="text-muted">To change your halal status or certificate, contact the FoodLoop team: an admin has to check it again.</p>
        </x-card>
    </div>
</x-layouts.seller>
