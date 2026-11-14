{{--
    /dev/layouts/dashboard · the dashboard layout filled like mockup c08 (local only).
    SAMPLE DATA. The real seller pages are built in Stage 5.
--}}
@php use App\Support\Money; @endphp

<x-layouts.seller title="Tonight" active="dashboard" :shop="$shop">
    <x-slot:subtitle>Sample data · this is a preview of the dashboard layout</x-slot:subtitle>
    <x-slot:actions>
        <x-button :href="route('dev.components')" variant="outline">All components</x-button>
        <x-button>New listing</x-button>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-stat-tile label="Waiting for pickup" value="2" variant="marigold" />
        <x-stat-tile label="Reservations today" value="9" />
        <x-stat-tile label="Sales this week" :value="Money::format(31200)" />
        <x-stat-tile label="Rescued this month" value="64" variant="forest" />
    </div>

    <x-card as="section" class="mt-6" padding="lg">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-2xl font-bold">Pickups</h2>
            <form class="flex items-center gap-2" onsubmit="return false">
                <label for="code" class="text-sm font-semibold">Code</label>
                <input id="code" inputmode="numeric" maxlength="4" placeholder="4 digits"
                       class="min-h-11 w-32 rounded-xl border-2 border-forest bg-cream px-3 font-mono tracking-wider placeholder:font-sans placeholder:tracking-normal placeholder:text-muted">
                <x-button variant="marigold" type="submit">Check</x-button>
            </form>
        </div>

        {{-- A table on wide screens, stacked cards on phones. --}}
        <div class="mt-4 hidden md:block">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b-2 border-forest text-[0.6875rem] uppercase tracking-wider text-muted">
                        <th class="px-2 py-2 font-bold">Code</th>
                        <th class="px-2 py-2 font-bold">Customer</th>
                        <th class="px-2 py-2 font-bold">Item</th>
                        <th class="px-2 py-2 font-bold">Qty</th>
                        <th class="px-2 py-2 font-bold">Status</th>
                        <th class="px-2 py-2"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pickups as [$code, $customer, $item, $quantity, $status])
                        <tr class="border-b border-line">
                            <td class="px-2 py-3 font-mono text-base font-bold tracking-widest">{{ $code }}</td>
                            <td class="px-2 py-3">{{ $customer }}</td>
                            <td class="px-2 py-3">{{ $item }}</td>
                            <td class="px-2 py-3">{{ $quantity }}</td>
                            <td class="px-2 py-3"><x-status-pill :status="$status" /></td>
                            <td class="px-2 py-1 text-right">
                                @if ($status === \App\Enums\ReservationStatus::Reserved)
                                    <x-button variant="outline">Mark collected</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="mt-4 space-y-3 md:hidden">
            @foreach ($pickups as [$code, $customer, $item, $quantity, $status])
                <li class="rounded-2xl border border-line p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-lg font-bold tracking-widest">{{ $code }}</span>
                        <x-status-pill :status="$status" />
                    </div>
                    <p class="mt-1 text-sm">{{ $customer }} · {{ $quantity }} × {{ $item }}</p>
                    @if ($status === \App\Enums\ReservationStatus::Reserved)
                        <x-button variant="outline" class="mt-3 w-full">Mark collected</x-button>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-card as="section" padding="lg">
            <h2 class="font-display text-2xl font-bold">Live listings</h2>

            <div class="mt-4 flex items-center gap-3">
                <x-food-photo :path="$bundle->photo_path" alt="" size="sm" />
                <p class="min-w-0 flex-1 truncate font-display font-bold">{{ $bundle->title }}</p>
                <p class="font-display text-xl font-bold">−{{ $bundle->discount_percent }}%</p>
            </div>
            <p class="mt-2 text-sm text-muted">{{ $bundle->price_formatted }} · {{ \App\Support\TimeLeft::label($bundle->pickup_end) }}</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-line" role="img" aria-label="2 of 5 reserved">
                <div class="h-full w-2/5 bg-marigold"></div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <p class="text-sm font-bold">2 of 5 reserved</p>
                <div class="flex gap-2">
                    <x-button variant="outline">Edit</x-button>
                    <x-button variant="outline">Pause</x-button>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3 border-t border-line pt-5">
                <x-food-photo :path="$croissants->photo_path" alt="" size="sm" />
                <p class="min-w-0 flex-1 truncate font-display font-bold">{{ $croissants->title }}</p>
                <x-tag>Sold out</x-tag>
            </div>
            <p class="mt-2 text-sm text-muted">{{ $croissants->type->label() }} · {{ $croissants->price_formatted }}</p>
        </x-card>

        <x-card as="section" variant="forest" padding="lg">
            <h2 class="font-display text-2xl font-bold">Left over at closing?</h2>
            <p class="mt-2 text-sm">Offer unsold items to a partner food bank instead of binning them.</p>
            <p class="mt-2 text-sm text-mist">Partner: [FOOD BANK PARTNER NAME]</p>
            <x-button variant="marigold" class="mt-4">Offer to food bank</x-button>
        </x-card>
    </div>
</x-layouts.seller>
