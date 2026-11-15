{{--
    Admin → Orders → one order: the facts, what happened and when, and the override
    that marks a waiting order collected. Data from Admin\OrderController@show.

    $confirming is true on the "are you sure?" step of the override (same page, ?confirm=collect).
--}}
@php
    use App\Enums\PaymentMethod;
    use App\Support\Money;
    use App\Support\OrderTimeline;
    use App\Support\PickupWindow;

    $customer = $order->customer;
    $seller = $listing->seller;
    $waiting = $order->isReserved();
    $otherOrders = fn (string $search) => route('admin.orders.index', ['tab' => 'all', 'q' => $search]);
@endphp

<x-layouts.admin title="Order {{ $order->pickup_code }}" active="orders">
    <x-slot:subtitle>{{ $order->quantity }} × {{ $listing->title }} · {{ $seller->business_name }}</x-slot:subtitle>
    <x-slot:actions>
        <x-button variant="outline" :href="route('admin.orders.index')">All orders</x-button>
    </x-slot:actions>

    @include('admin.partials.flash')

    @error('confirmed')
        <x-notice variant="danger" class="mb-6">{{ $message }}</x-notice>
    @enderror

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[3fr_2fr]">
        <div class="space-y-6">
            <x-card as="section" padding="lg">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-2xl font-bold">The order</h2>
                    <span class="flex flex-wrap items-center gap-1.5">
                        <x-status-pill :status="$order->status" />
                        @if ($order->isAdminOverride())
                            <x-tag variant="outline">Admin override</x-tag>
                        @endif
                    </span>
                </div>

                <x-spec-grid class="mt-4" :items="[
                    'Pickup code' => $order->pickup_code,
                    'Amount' => Money::format($order->total_sen),
                    'Portions' => $order->quantity.' × '.$listing->price_formatted,
                    'Payment' => ($order->payment_method === PaymentMethod::PayNow ? 'Pay now' : 'Pay at pickup').' · '.$order->payment_status->label(),
                    'Pickup window' => PickupWindow::withDay($listing->pickup_start, $listing->pickup_end),
                    'Held until' => OrderTimeline::moment($order->expires_at),
                ]" />

                <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-[0.6875rem] font-bold uppercase tracking-wider text-muted">Customer</dt>
                        <dd class="mt-0.5">
                            <span class="block font-semibold">{{ $customer->name }}</span>
                            <span class="block text-muted [overflow-wrap:anywhere]">{{ $customer->email }}</span>
                            <span class="block text-muted">@include('admin.partials.customer-limit', ['customer' => $customer])</span>
                            <a href="{{ $otherOrders($customer->email) }}" class="flex min-h-11 items-center font-semibold underline">All orders by this customer</a>
                            @if ($customer->isBlocked() || $customer->no_show_strikes > 0)
                                <a href="{{ route('admin.customers.index') }}" class="flex min-h-11 items-center font-semibold underline">Strikes and limits</a>
                            @endif
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[0.6875rem] font-bold uppercase tracking-wider text-muted">Listing and shop</dt>
                        <dd class="mt-0.5">
                            <span class="block font-semibold">{{ $listing->title }}</span>
                            <span class="block text-muted">{{ $seller->business_name }} · {{ $listing->halal_status->label() }}</span>
                            <a href="{{ route('listings.show', $listing) }}" class="flex min-h-11 items-center font-semibold underline">Open the listing</a>
                            <a href="{{ $otherOrders($seller->business_name) }}" class="flex min-h-11 items-center font-semibold underline">All orders at this shop</a>
                        </dd>
                    </div>
                </dl>
            </x-card>

            @if ($waiting)
                <x-card as="section" :variant="$confirming ? 'marigold' : 'outline'" padding="lg" id="override">
                    <h2 class="font-display text-2xl font-bold">
                        {{ $confirming ? 'Mark order '.$order->pickup_code.' collected now?' : 'Admin override' }}
                    </h2>

                    @if ($confirming)
                        <p class="mt-2 text-sm">
                            This skips the pickup window and the shop’s code check. {{ $customer->name }} gets the normal “Collected” message,
                            it counts in the impact numbers, {{ $order->payment_method === PaymentMethod::PayNow ? '' : 'the order counts as paid (simulated), ' }}and
                            the order is marked as collected by you. It can’t be undone.
                        </p>
                        <form method="POST" action="{{ route('admin.orders.collect', $order) }}" class="mt-4 flex flex-wrap gap-3">
                            @csrf
                            <input type="hidden" name="confirmed" value="1">
                            <x-button type="submit" data-busy="Marking collected…">Yes, mark collected</x-button>
                            <x-button variant="outline" :href="route('admin.orders.show', $order)">No, keep it waiting</x-button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-muted">
                            Normally the shop types the customer’s code during the pickup window. For a demo, or to fix a pickup the shop
                            forgot to record, you can mark this order collected right now. You’ll be asked to confirm.
                        </p>
                        <x-button variant="marigold" class="mt-4" :href="route('admin.orders.show', [$order, 'confirm' => 'collect']).'#override'">
                            Mark collected (admin override)
                        </x-button>
                    @endif
                </x-card>
            @endif
        </div>

        <x-card as="section" padding="lg">
            <h2 class="font-display text-2xl font-bold">What happened</h2>
            <ol class="mt-4 space-y-4">
                @foreach ($timeline as $step)
                    <li class="flex gap-3">
                        <span @class([
                            'mt-1.5 size-3 shrink-0 rounded-full border-2 border-forest',
                            'bg-forest' => $step['at'] !== null,
                            'bg-marigold' => $step['at'] === null,
                        ]) aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="font-display font-bold leading-snug">{{ $step['title'] }}</p>
                            <p class="text-xs font-semibold text-muted">{{ $step['at'] ? OrderTimeline::moment($step['at']) : 'Not yet' }}</p>
                            @if ($step['note'])
                                <p class="mt-1 text-sm text-muted">{{ $step['note'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>
    </div>
</x-layouts.admin>
