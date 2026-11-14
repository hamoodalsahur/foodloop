{{-- Every pickup that is waiting or happened today, including later days. --}}
<x-layouts.seller title="Pickups" active="pickups">
    <x-slot:subtitle>Type the customer’s code, or tap “Mark collected” once the window is open.</x-slot:subtitle>
    @include('seller.partials.flash')
    <x-card>
        @include('seller.partials.pickups-table', ['pickups' => $pickups, 'showDay' => true])
    </x-card>
</x-layouts.seller>
