{{--
    One reservation with its pickup code (mockup c03). Only the customer who made it.
    $phase is: before_window, window_open, grace (all still "reserved"),
    or collected, cancelled, no_show.
    Data comes from App\Http\Controllers\OrderController@show.
--}}
@php
    use App\Enums\PaymentStatus;
    use App\Enums\ReservationStatus;
    use App\Support\Distance;
    use App\Support\PickupWindow;

    $seller = $listing->seller;
    $waiting = $reservation->isReserved();
    $user = auth()->user();

    [$heading, $subheading] = match ($reservation->status) {
        ReservationStatus::Reserved => ['You’re booked.', 'Show this code at '.$seller->business_name.'.'],
        ReservationStatus::Collected => ['Collected. Enjoy!', 'Picked up at '.$seller->business_name.' '.str(PickupWindow::day($reservation->collected_at))->lower().' at '.PickupWindow::time($reservation->collected_at).'.'],
        ReservationStatus::Cancelled => ['Reservation cancelled.', 'The food went back on sale for someone else.'],
        ReservationStatus::NoShow => ['You missed this pickup.', 'It wasn’t collected in time at '.$seller->business_name.'.'],
    };

    // The countdown: what are we waiting for, and until when?
    [$countdownLabel, $countdownUntil] = match ($phase) {
        'before_window' => ['Pickup window opens in', $listing->pickup_start],
        'window_open' => ['Pickup window closes in', $listing->pickup_end],
        'grace' => ['Last chance: collect within', $reservation->expires_at],
        default => [null, null],
    };

    // The three-step bar: how many steps are filled.
    $stepsDone = match ($phase) {
        'before_window' => 1,
        'window_open', 'grace' => 2,
        'collected' => 3,
        default => 0,
    };

    $payment = match ($reservation->payment_status) {
        PaymentStatus::SimulatedPaid => 'Paid (simulated)',
        PaymentStatus::Refunded => 'Refunded (simulated)',
        PaymentStatus::Unpaid => $waiting ? 'Pay at pickup' : 'Nothing paid',
    };

    $strikesToBlock = config('foodloop.strikes_to_block');
@endphp

<x-layouts.customer title="Your pickup code" active="orders" :header="false" flush>
    {{-- Dark top block: status, headline, the code --}}
    <div class="bg-forest px-5 pb-5 pt-5 text-cream md:mx-5 md:mt-5 md:rounded-3xl">
        <div class="flex items-center justify-between gap-3">
            <x-back-link :href="route('orders.index')" label="Back to orders" on="forest" />
            <x-tag :variant="in_array($reservation->status, [ReservationStatus::Reserved, ReservationStatus::Collected]) ? 'marigold' : 'cream'" class="px-3 py-1 text-xs">
                {{ $reservation->status->label() }}
            </x-tag>
        </div>

        <h1 class="mt-3 font-display text-4xl font-bold tracking-tight">{{ $heading }}</h1>
        <p class="mt-1 text-sm text-mist">{{ $subheading }}</p>

        @if ($waiting)
            <div class="mt-4 flex items-center justify-between gap-4 rounded-3xl bg-marigold px-5 py-4 text-forest">
                <p class="text-xs font-bold uppercase leading-tight tracking-widest">Pickup<br>code</p>
                <p class="font-display text-6xl font-bold leading-none tracking-wider" aria-label="Pickup code {{ implode(' ', str_split($reservation->pickup_code)) }}">
                    {{ $reservation->pickup_code }}
                </p>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col px-5 pb-6 pt-5">
        @if (session('status'))
            <x-notice variant="success" class="mb-4">{{ session('status') }}</x-notice>
        @endif

        @error('reservation')
            <x-notice variant="danger" class="mb-4">{{ $message }}</x-notice>
        @enderror

        @if ($waiting)
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-muted">{{ $countdownLabel }}</p>
                <x-countdown :until="$countdownUntil" class="text-2xl" />
            </div>
        @endif

        @if ($stepsDone > 0)
            <ol class="mt-2 grid grid-cols-3 gap-2 text-xs">
                @foreach (['Reserved', 'Window open', 'Collected'] as $index => $step)
                    <li @if ($index + 1 === $stepsDone) aria-current="step" @endif>
                        <span @class(['block h-1.5 rounded-full', 'bg-forest' => $index < $stepsDone, 'bg-line' => $index >= $stepsDone])></span>
                        <span @class(['mt-1.5 block', 'font-bold text-forest' => $index + 1 === $stepsDone, 'text-muted' => $index + 1 !== $stepsDone])>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        @endif

        <x-spec-grid class="mt-5" :items="[
            'Item' => $listing->title.' ('.$reservation->quantity.' '.Str::plural('portion', $reservation->quantity).')',
            'Pickup' => PickupWindow::withDay($listing->pickup_start, $listing->pickup_end),
            'Where' => $seller->business_name.($listing->distanceKm !== null ? ' · '.Distance::label($listing->distanceKm) : ''),
            'Payment' => $reservation->total_formatted.' · '.$payment,
        ]" />

        @if ($waiting)
            {{-- What cancelling would mean right now. --}}
            @if ($reservation->isFreeToCancel())
                <x-notice class="mt-4">
                    Free cancellation until <strong>{{ PickupWindow::time($reservation->freeCancelUntil()) }}</strong>{{ $reservation->freeCancelUntil()->isToday() ? '' : ' '.str(PickupWindow::day($reservation->freeCancelUntil()))->lower() }}.
                    After that it counts as a no-show.
                </x-notice>
            @else
                <x-notice class="mt-4">
                    Free cancellation has ended. <strong>Cancelling now counts as a no-show.</strong>
                    {{ $strikesToBlock }} no-shows pause reserving for {{ config('foodloop.block_days') }} days.
                </x-notice>
            @endif

            <div class="mt-4" x-data="{ confirming: false }">
                <div class="grid grid-cols-2 gap-3" x-show="! confirming">
                    @if ($seller->lat !== null && $seller->lng !== null)
                        <x-button href="https://www.google.com/maps/dir/?api=1&destination={{ $seller->lat }},{{ $seller->lng }}"
                                  target="_blank" rel="noopener" class="min-h-13">
                            Directions
                        </x-button>
                    @endif
                    <x-button variant="outline" @click="confirming = true" class="min-h-13">Cancel</x-button>
                </div>

                {{-- Second step, so nobody cancels by a slip of the thumb. (Without JavaScript the <noscript> form below is used.) --}}
                <form method="POST" action="{{ route('orders.destroy', $reservation) }}" x-show="confirming" x-cloak
                      class="rounded-3xl border-2 border-forest bg-white p-4">
                    @csrf
                    @method('DELETE')
                    <p class="font-display text-lg font-bold">Cancel this reservation?</p>
                    <p class="mt-1 text-sm text-muted">
                        @if ($reservation->isFreeToCancel())
                            It’s free right now. The food goes back on sale.
                        @else
                            This is a late cancel, so it counts as a no-show.
                        @endif
                    </p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <x-button variant="outline" @click="confirming = false">Keep it</x-button>
                        <x-button type="submit">Yes, cancel</x-button>
                    </div>
                </form>
                <noscript>
                    <form method="POST" action="{{ route('orders.destroy', $reservation) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="outline">Yes, cancel this reservation</x-button>
                    </form>
                </noscript>
            </div>
        @elseif ($reservation->status === ReservationStatus::NoShow || $reservation->late_cancel)
            <x-notice variant="danger" class="mt-4">
                This counted as a no-show.
                @if ($user->isBlocked())
                    You can’t reserve until <strong>{{ $user->blocked_until->format('D j M, g:i A') }}</strong>.
                @else
                    You have {{ $user->no_show_strikes }} of {{ $strikesToBlock }} strikes. At {{ $strikesToBlock }}, reserving pauses for {{ config('foodloop.block_days') }} days.
                @endif
            </x-notice>
        @endif

        @unless ($waiting)
            <div class="mt-auto pt-6">
                <x-button size="lg" :href="route('deals.index')" arrow>Find another deal</x-button>
            </div>
        @endunless
    </div>
</x-layouts.customer>
