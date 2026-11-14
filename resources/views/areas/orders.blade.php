{{-- Placeholder until Stage 4 builds the real orders page. Customers only. --}}
<x-layouts.customer title="Orders" active="orders">
    <h1 class="font-display text-4xl font-bold tracking-tight">Orders</h1>

    <x-empty-state class="mt-6"
                   title="Your orders will show up here"
                   message="This page is still being built. Reserving and picking up food comes next.">
        <x-button variant="outline" :href="route('home')">Back to the start</x-button>
    </x-empty-state>
</x-layouts.customer>
