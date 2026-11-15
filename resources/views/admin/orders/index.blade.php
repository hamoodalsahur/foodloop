{{--
    Admin → Orders: every order on FoodLoop across all shops, newest first (the audit view).
    Data from Admin\OrderController@index (App\Queries\AdminOrders).

    A table from md: up; on phones each order becomes a small card, so nothing hides off the edge.
--}}
@php
    use App\Enums\PaymentMethod;
    use App\Enums\ReservationStatus;
    use App\Queries\AdminOrders;
    use App\Support\CustomerName;
    use App\Support\Money;

    // Links keep the search; the default tab and an empty search stay out of the address.
    $link = fn (string $key) => route('admin.orders.index', array_filter([
        'tab' => $key === AdminOrders::DEFAULT_TAB ? null : $key,
        'q' => $search !== '' ? $search : null,
    ]));

    $when = fn ($moment) => $moment?->format('j M, g:i A');
    $label = 'text-[0.6875rem] font-bold uppercase tracking-wider text-muted md:hidden';
    $cell = 'max-md:flex max-md:items-baseline max-md:justify-between max-md:gap-4 max-md:py-1 md:py-3 md:pr-3 md:align-top';
@endphp

<x-layouts.admin title="Orders" active="orders">
    <x-slot:subtitle>Every order across all shops, newest first. Payments are simulated; no real money moves.</x-slot:subtitle>

    @include('admin.partials.flash')

    <form method="GET" action="{{ route('admin.orders.index') }}" role="search" class="flex flex-wrap items-end gap-2">
        @if ($tab !== AdminOrders::DEFAULT_TAB)
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif
        <div class="min-w-0 flex-1 basis-64">
            <label for="q" class="block text-sm font-semibold">Search orders</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" autocomplete="off"
                   placeholder="Pickup code, customer or shop"
                   class="mt-1.5 block min-h-11 w-full rounded-xl border-2 border-forest bg-cream px-3">
        </div>
        <x-button type="submit">Search</x-button>
        @if ($search !== '')
            <x-button variant="outline" :href="route('admin.orders.index', $tab === AdminOrders::DEFAULT_TAB ? [] : ['tab' => $tab])">Clear</x-button>
        @endif
    </form>

    <nav aria-label="Which orders" class="mt-5 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $item)
            <a href="{{ $link($key) }}"
               @if ($tab === $key) aria-current="page" @endif
               @class([
                   'flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold',
                   'bg-forest text-cream' => $tab === $key,
                   'bg-white text-forest hover:bg-line' => $tab !== $key,
               ])>
                {{ $item['label'] }}
                <span @class(['text-xs font-bold', 'text-mist' => $tab === $key, 'text-muted' => $tab !== $key])>{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Phones: the two counts side by side, the money (the longest number) on its own row. --}}
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-stat-tile label="Orders" :value="number_format($totals['orders'])" />
        <x-stat-tile label="Money" :value="Money::format($totals['sen'])" class="max-sm:order-last max-sm:col-span-2" />
        <x-stat-tile label="Portions" :value="number_format($totals['portions'])" />
    </div>
    <p class="mt-2 text-xs text-muted">
        Added up over {{ $search !== '' ? 'every order that matches “'.$search.'”' : 'every order' }} in “{{ $tabs[$tab]['label'] }}”, not only the page shown.
    </p>

    @if ($orders->isEmpty())
        <x-empty-state class="mt-6"
                       :title="$search !== '' ? 'No orders match “'.$search.'”' : 'No orders here'"
                       :message="$search !== ''
                           ? 'Try a pickup code, part of a customer’s name or email, or a shop name. Other tabs may still have matches.'
                           : 'Orders show up here as soon as students reserve food.'">
            @if ($tab !== 'all')
                <x-button variant="outline" :href="$link('all')">Look in all orders</x-button>
            @endif
        </x-empty-state>
    @else
        <x-card class="mt-6 max-md:bg-transparent max-md:p-0" padding="lg">
            <table class="w-full text-left text-sm max-md:block">
                <caption class="sr-only">Orders, newest first</caption>
                <thead class="max-md:sr-only">
                    <tr class="border-b-2 border-forest text-[0.6875rem] font-bold uppercase tracking-wider text-muted">
                        <th scope="col" class="py-2 pr-3">Code</th>
                        <th scope="col" class="py-2 pr-3">Customer</th>
                        <th scope="col" class="py-2 pr-3">Item and shop</th>
                        <th scope="col" class="py-2 pr-3">Amount</th>
                        <th scope="col" class="py-2 pr-3">Status</th>
                        <th scope="col" class="py-2 pr-3">Reserved</th>
                        <th scope="col" class="py-2 pr-3">Closed</th>
                        <th scope="col" class="py-2"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="max-md:block max-md:space-y-3">
                    @foreach ($orders as $order)
                        @php
                            $closedAt = match ($order->status) {
                                ReservationStatus::Collected => $order->collected_at,
                                ReservationStatus::Cancelled => $order->cancelled_at,
                                ReservationStatus::NoShow => $order->expires_at,
                                default => null,
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 max-md:block max-md:rounded-2xl max-md:border-2 max-md:border-line max-md:bg-white max-md:p-4 max-md:last:border-b-2">
                            <td class="{{ $cell }} font-display text-base font-bold tracking-widest max-md:text-xl">
                                <span class="{{ $label }} font-sans tracking-wider">Code</span>
                                {{ $order->pickup_code }}
                            </td>
                            <td class="{{ $cell }}">
                                <span class="{{ $label }}">Customer</span>
                                <span class="min-w-0 max-md:text-right">
                                    <span class="block font-semibold">{{ CustomerName::short($order->customer->name) }}</span>
                                    <span class="block text-xs text-muted max-md:[overflow-wrap:anywhere] md:max-w-44 md:truncate" title="{{ $order->customer->email }}">{{ $order->customer->email }}</span>
                                </span>
                            </td>
                            <td class="{{ $cell }}">
                                <span class="{{ $label }}">Item</span>
                                <span class="min-w-0 max-md:text-right">
                                    <span class="block font-semibold">{{ $order->listing->title }} × {{ $order->quantity }}</span>
                                    <span class="block text-xs text-muted">{{ $order->listing->seller->business_name }}</span>
                                </span>
                            </td>
                            <td class="{{ $cell }} md:whitespace-nowrap">
                                <span class="{{ $label }}">Amount</span>
                                <span class="max-md:text-right">
                                    <span class="block font-semibold">{{ Money::format($order->total_sen) }}</span>
                                    <span class="block text-xs text-muted">{{ $order->payment_method === PaymentMethod::PayNow ? 'Pay now' : 'Pay at pickup' }}</span>
                                </span>
                            </td>
                            <td class="{{ $cell }}">
                                <span class="{{ $label }}">Status</span>
                                <span class="flex flex-wrap items-center gap-1.5 max-md:justify-end">
                                    <x-status-pill :status="$order->status" />
                                    @if ($order->isAdminOverride())
                                        <x-tag variant="outline">Admin override</x-tag>
                                    @endif
                                </span>
                            </td>
                            <td class="{{ $cell }} text-muted md:whitespace-nowrap">
                                <span class="{{ $label }}">Reserved</span>
                                {{ $when($order->created_at) }}
                            </td>
                            <td class="{{ $cell }} text-muted md:whitespace-nowrap">
                                <span class="{{ $label }}">{{ $closedAt ? $order->status->label() : 'Closed' }}</span>
                                @if ($closedAt)
                                    <span>{{ $when($closedAt) }}</span>
                                @else
                                    <span>Not yet</span>
                                @endif
                            </td>
                            <td class="max-md:mt-3 max-md:block md:py-2 md:text-right">
                                <x-button variant="outline" :href="route('admin.orders.show', $order)" class="max-md:w-full">
                                    View<span class="sr-only"> order {{ $order->pickup_code }}</span>
                                </x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <nav aria-label="More orders" class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">
                Showing {{ number_format($orders->firstItem()) }}–{{ number_format($orders->lastItem()) }} of {{ number_format($orders->total()) }}
                @if ($orders->hasPages())
                    · page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}
                @endif
            </p>
            @if ($orders->hasPages())
                <div class="flex gap-2">
                    @unless ($orders->onFirstPage())
                        <x-button variant="outline" :href="$orders->previousPageUrl()">Newer</x-button>
                    @endunless
                    @if ($orders->hasMorePages())
                        <x-button variant="outline" :href="$orders->nextPageUrl()">Older</x-button>
                    @endif
                </div>
            @endif
        </nav>
    @endif
</x-layouts.admin>
