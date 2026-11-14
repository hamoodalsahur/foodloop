{{--
    /dev/layouts/customer · the customer layout filled like mockup c01 (local only).
    SAMPLE DATA. The real deals page is built in Stage 4.
--}}
<x-layouts.customer title="Customer layout" active="deals">
    <x-slot:notice>Sample data · this is a preview of the customer layout</x-slot:notice>

    <div class="grid grid-cols-2 gap-3">
        <x-card variant="marigold" class="row-span-2 flex min-h-48 flex-col justify-between">
            <p class="text-sm font-semibold">Surprise Bundles</p>
            <div>
                <p class="font-display text-7xl font-bold leading-none tracking-tight">8</p>
                <p class="mt-1 text-sm font-semibold">left tonight</p>
            </div>
        </x-card>
        <x-card variant="forest">
            <p class="text-xs text-mist">Filter</p>
            <p class="mt-1 flex items-center gap-2 font-display font-bold">Halal only <x-tag variant="marigold">On</x-tag></p>
        </x-card>
        <x-card variant="outline">
            <p class="text-xs text-muted">Distance</p>
            <p class="mt-1 font-display font-bold">Within 2 km</p>
        </x-card>
    </div>

    <h1 class="sr-only">Deals</h1>

    <ul class="mt-4 space-y-3">
        @foreach ($listings as $listing)
            <li><x-deal-row :listing="$listing" href="#" :distance="['0.4 km', '0.9 km', '0.6 km', '0.3 km', '0.6 km'][$loop->index]" /></li>
        @endforeach
    </ul>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('dev.components') }}" class="font-semibold underline underline-offset-4">Back to all components</a>
    </p>
</x-layouts.customer>
