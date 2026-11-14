{{--
    The pickups table (mockup c08) with the code check above it.
    Pass `pickups` (reservations with listing and customer loaded) and
    optionally `showDay` to print the day next to the pickup time.
--}}
@php
    use App\Enums\ReservationStatus;
    use App\Support\CustomerName;
    use App\Support\PickupWindow;
    $showDay ??= false;
@endphp

<div class="flex flex-wrap items-end justify-between gap-4">
    <h2 class="font-display text-2xl font-bold">Pickups</h2>
    <form method="POST" action="{{ route('seller.pickups.collect') }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div>
            <label for="code" class="block text-sm font-semibold">Customer’s code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="off" maxlength="4" placeholder="4 digits"
                   value="{{ old('code') }}"
                   @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                   class="mt-1.5 block min-h-12 w-32 rounded-xl border-2 border-forest bg-cream px-3 font-display text-xl tracking-[0.2em]">
        </div>
        <x-button type="submit" variant="marigold" class="min-h-12">Check and collect</x-button>
    </form>
</div>
@error('code')
    <x-notice id="code-error" variant="danger" class="mt-3">{{ $message }}</x-notice>
@enderror

@if ($pickups->isEmpty())
    <x-empty-state class="mt-4" title="No pickups yet today" message="They show up here as students reserve your listings." />
@else
    <div class="relative mt-4 overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead>
                <tr class="border-b-2 border-forest text-[0.6875rem] font-bold uppercase tracking-wider text-muted">
                    <th scope="col" class="py-2 pr-3">Code</th>
                    <th scope="col" class="py-2 pr-3">Customer</th>
                    <th scope="col" class="py-2 pr-3">Item</th>
                    <th scope="col" class="py-2 pr-3">Qty</th>
                    <th scope="col" class="py-2 pr-3">Pickup</th>
                    <th scope="col" class="py-2 pr-3">Status</th>
                    <th scope="col" class="py-2"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pickups as $pickup)
                    @php
                        $listing = $pickup->listing;
                        $canCollect = $pickup->status === ReservationStatus::Reserved
                            && now()->greaterThanOrEqualTo($listing->pickup_start)
                            && now()->lessThanOrEqualTo($pickup->expires_at);
                    @endphp
                    <tr class="border-b border-line">
                        <td class="py-3 pr-3 font-display text-base font-bold tracking-widest">{{ $pickup->pickup_code }}</td>
                        <td class="py-3 pr-3 whitespace-nowrap">{{ CustomerName::short($pickup->customer->name) }}</td>
                        <td class="py-3 pr-3 whitespace-nowrap">{{ $listing->title }}</td>
                        <td class="py-3 pr-3">{{ $pickup->quantity }}</td>
                        <td class="py-3 pr-3 whitespace-nowrap text-muted">{{ $showDay ? PickupWindow::withDay($listing->pickup_start, $listing->pickup_end) : PickupWindow::times($listing->pickup_start, $listing->pickup_end) }}</td>
                        <td class="py-3 pr-3"><x-status-pill :status="$pickup->status" /></td>
                        <td class="py-2 text-right whitespace-nowrap">
                            @if ($canCollect)
                                <form method="POST" action="{{ route('seller.pickups.collect') }}">
                                    @csrf
                                    <input type="hidden" name="code" value="{{ $pickup->pickup_code }}">
                                    <x-button type="submit" variant="outline" aria-label="Mark {{ $pickup->pickup_code }} collected">Mark collected</x-button>
                                </form>
                            @elseif ($pickup->status === ReservationStatus::Reserved && now()->lessThan($listing->pickup_start))
                                <span class="text-xs text-muted">From {{ PickupWindow::time($listing->pickup_start) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
