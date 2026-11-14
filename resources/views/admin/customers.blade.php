{{-- Customers who are limited or have strikes. Data from Admin\CustomerController@index. --}}
<x-layouts.admin title="Customers" active="customers">
    <x-slot:subtitle>
        {{ config('foodloop.strikes_to_block') }} no-shows = no reserving for {{ config('foodloop.block_days') }} days. Only customers with a strike or a limit are listed.
    </x-slot:subtitle>

    @include('admin.partials.flash')

    @if ($customers->isEmpty())
        <x-empty-state title="Nobody has a strike" message="Customers who miss a pickup or cancel late show up here." />
    @else
        <ul class="space-y-3">
            @foreach ($customers as $customer)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-4">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $customer->name }}</p>
                        <p class="truncate text-sm text-muted">{{ $customer->email }}</p>
                        <p class="mt-1 text-sm">
                            @include('admin.partials.customer-limit', ['customer' => $customer])
                            @if ($customer->isBlocked())
                                <span class="text-muted">· until {{ $customer->blocked_until->format('D j M, g:i A') }}</span>
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.customers.lift-limit', $customer) }}">
                        @csrf
                        <x-button type="submit" variant="outline">{{ $customer->isBlocked() ? 'Lift limit' : 'Clear strikes' }}<span class="sr-only"> for {{ $customer->name }}</span></x-button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
