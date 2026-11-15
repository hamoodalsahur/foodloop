{{--
    The partner food bank and the leftovers offered to it. Data from Admin\FoodBankController.
--}}
@php
    use App\Enums\DonationStatus;
@endphp

<x-layouts.admin title="Food banks" active="food-banks">
    <x-slot:subtitle>Leftovers from closed listings are offered to the partner. Record here what happened to each offer.</x-slot:subtitle>

    @include('admin.partials.flash')

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-tile label="Portions waiting for the food bank" :value="number_format($portions['waiting'])" :variant="$portions['waiting'] > 0 ? 'marigold' : 'outline'" />
        <x-stat-tile label="Portions collected" :value="number_format($portions['collected'])" variant="forest" />
        <x-stat-tile label="Portions declined" :value="number_format($portions['declined'])" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1fr_2fr] xl:items-start">
        <section class="space-y-3">
            <h2 class="font-display text-2xl font-bold">Partner</h2>
            @forelse ($partners as $partner)
                <form method="POST" action="{{ route('admin.food-banks.update', $partner) }}" class="space-y-4 rounded-3xl bg-white p-5">
                    @csrf
                    @method('PUT')
                    @if (str_starts_with($partner->name, '['))
                        <x-notice>This is still the placeholder. Put in the real partner once the team has one.</x-notice>
                    @endif
                    <x-form.input name="name" label="Name" :id="'partner-name-'.$partner->id" :value="$partner->name" required maxlength="120" />
                    <x-form.input name="contact" label="Contact (person, phone or email)" :id="'partner-contact-'.$partner->id" :value="$partner->contact" maxlength="255" />
                    <div class="flex justify-end">
                        <x-button type="submit">Save details</x-button>
                    </div>
                </form>
            @empty
                <x-empty-state title="No food bank partner yet" message="Run the demo seeder to add the placeholder partner." />
            @endforelse
        </section>

        <section>
            <h2 class="font-display text-2xl font-bold">Leftover offers</h2>
            @forelse ($donations as $donation)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-4">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $donation->listing->title }}</p>
                        <p class="text-sm text-muted">
                            {{ $donation->quantity }} {{ Str::plural('portion', $donation->quantity) }} · {{ $donation->listing->seller->business_name }} · to {{ $donation->partner->name }} · offered {{ $donation->created_at->format('j M, g:i A') }}
                        </p>
                    </div>
                    @if ($donation->status === DonationStatus::Offered)
                        <form method="POST" action="{{ route('admin.donations.settle', $donation) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <x-button type="submit" name="outcome" value="collected">Mark collected<span class="sr-only">: {{ $donation->listing->title }}</span></x-button>
                            <x-button type="submit" name="outcome" value="declined" variant="outline">Declined<span class="sr-only">: {{ $donation->listing->title }}</span></x-button>
                        </form>
                    @else
                        <x-tag :variant="$donation->status === DonationStatus::Collected ? 'forest' : 'outline'">{{ $donation->status->label() }}</x-tag>
                    @endif
                </div>
            @empty
                <x-empty-state class="mt-3" title="No leftovers offered yet" message="Offers show up here when a listing closes with portions left." />
            @endforelse
        </section>
    </div>
</x-layouts.admin>
