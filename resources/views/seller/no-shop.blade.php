{{-- A seller account without a shop profile (shouldn't happen after sign-up, but never a blank page). --}}
<x-layouts.seller title="Dashboard" active="dashboard" :shop="null">
    <x-empty-state title="No shop on this account" message="This seller account has no shop details yet. Please contact the FoodLoop team." />
</x-layouts.seller>
