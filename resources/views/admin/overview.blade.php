{{--
    The admin's home page (mockup c10). Data from App\Http\Controllers\Admin\OverviewController.
--}}
@php
    use App\Support\CustomerName;
    use App\Support\Money;
@endphp

<x-layouts.admin title="Overview" active="overview">
    <x-slot:subtitle>Things that need a decision come first.</x-slot:subtitle>

    @include('admin.partials.flash')

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-stat-tile label="Sellers to approve" :value="number_format($stats['sellersToApprove'])" :variant="$stats['sellersToApprove'] > 0 ? 'marigold' : 'outline'" />
        <x-stat-tile label="Open reports" :value="number_format($stats['openReports'])" :variant="$stats['openReports'] > 0 ? 'marigold' : 'outline'" />
        <x-stat-tile label="Live listings" :value="number_format($stats['liveListings'])" />
        <x-stat-tile label="Meals rescued this month" :value="number_format($stats['mealsThisMonth'])" variant="forest" />
    </div>

    <x-card as="section" class="mt-6" padding="lg">
        <h2 class="font-display text-2xl font-bold">Sellers to approve</h2>
        <div class="mt-4">
            @if ($sellers->isEmpty())
                <x-empty-state title="No shops are waiting" message="New shops show up here as soon as they sign up." />
            @else
                @include('admin.partials.sellers-table', ['sellers' => $sellers])
            @endif
        </div>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <x-card as="section" padding="lg">
            <h2 class="font-display text-2xl font-bold">Reported listings</h2>
            <div class="mt-4 space-y-3">
                @forelse ($reported as $listing)
                    @include('admin.partials.reported-listing', ['listing' => $listing])
                @empty
                    <x-empty-state title="No open reports" message="When a customer reports a listing, it shows up here." />
                @endforelse
            </div>
        </x-card>

        <x-card as="section" padding="lg">
            <h2 class="font-display text-2xl font-bold">No-show limits</h2>
            <p class="mt-1 text-sm text-muted">
                {{ config('foodloop.strikes_to_block') }} no-shows = no reserving for {{ config('foodloop.block_days') }} days.
            </p>
            @if ($customers->isEmpty())
                <p class="mt-4 text-sm text-muted">Nobody has a strike right now.</p>
            @else
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($customers->take(8) as $customer)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span>{{ CustomerName::short($customer->name) }}</span>
                            @include('admin.partials.customer-limit', ['customer' => $customer])
                        </li>
                    @endforeach
                </ul>
                <x-button variant="outline" :href="route('admin.customers.index')" class="mt-3">All customers with strikes</x-button>
            @endif
        </x-card>
    </div>

    <x-card as="section" class="mt-6" padding="lg">
        <h2 class="font-display text-2xl font-bold">Platform totals</h2>
        <p class="mt-1 text-sm text-muted">Since the start. Payments are simulated, so the money saved is what the listed prices add up to.</p>
        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-5 md:grid-cols-5">
            @foreach ([
                'Customers' => number_format($totals['customers']),
                'Approved sellers' => number_format($totals['approvedSellers']),
                'Meals rescued' => number_format($totals['mealsRescued']),
                'Saved by customers' => Money::format($totals['savedSen']),
                'Portions to food banks' => number_format($totals['portionsDonated']),
            ] as $label => $value)
                <div>
                    <dt class="text-[0.6875rem] font-bold uppercase tracking-wider text-muted">{{ $label }}</dt>
                    <dd class="mt-0.5 font-display text-3xl font-bold tracking-tight">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-card>
</x-layouts.admin>
