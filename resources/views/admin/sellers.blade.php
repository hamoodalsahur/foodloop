{{-- Every shop, grouped by status, with the decisions an admin can take. Data from Admin\SellerController@index. --}}
<x-layouts.admin title="Sellers" active="sellers">
    <x-slot:subtitle>Approve a shop once its SSM number and halal claim check out.</x-slot:subtitle>

    @include('admin.partials.flash')

    @foreach ($groups as $heading => $sellers)
        @continue($sellers->isEmpty() && $heading !== 'Waiting for approval')

        <x-card as="section" class="mb-6" padding="lg">
            <h2 class="font-display text-2xl font-bold">{{ $heading }} <span class="text-muted">({{ $sellers->count() }})</span></h2>
            <div class="mt-4">
                @if ($sellers->isEmpty())
                    <x-empty-state title="No shops are waiting" message="New shops show up here as soon as they sign up." />
                @else
                    @include('admin.partials.sellers-table', ['sellers' => $sellers])
                @endif
            </div>
        </x-card>
    @endforeach
</x-layouts.admin>
