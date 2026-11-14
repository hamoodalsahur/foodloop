{{--
    The dashboard layout with the seller's menu and shop box filled in.
    Every seller page starts with this:

    <x-layouts.seller title="Tonight" active="dashboard">
        <x-slot:subtitle>…</x-slot:subtitle>     (optional)
        <x-slot:actions>…</x-slot:actions>       (optional)
        …page content…
    </x-layouts.seller>

    `active` is one of: dashboard, listings, pickups, donations, profile.
    `shop` defaults to the signed-in seller's shop.
--}}
@props(['title', 'active' => null, 'shop' => null])

@php
    $link = fn (string $route) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#';
    $shop ??= auth()->user()?->seller;

    $nav = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => $link('seller.dashboard')],
        ['key' => 'listings', 'label' => 'Listings', 'href' => $link('seller.listings.index')],
        ['key' => 'pickups', 'label' => 'Pickups', 'href' => $link('seller.pickups.index')],
        ['key' => 'donations', 'label' => 'Donations', 'href' => $link('seller.donations.index')],
        ['key' => 'profile', 'label' => 'Shop profile', 'href' => $link('seller.profile.edit')],
    ];
@endphp

<x-layouts.dashboard :title="$title" area="Seller" :nav="$nav" :active="$active">
    @if ($shop)
        <x-slot:identity>
            <p class="truncate font-bold">{{ $shop->business_name }}</p>
            <p class="truncate text-xs text-mist">
                {{ $shop->isApproved() ? 'Verified' : $shop->status->label() }} · {{ $shop->halal_claim->label() }}
            </p>
        </x-slot:identity>
    @endif

    @isset($subtitle)
        <x-slot:subtitle>{{ $subtitle }}</x-slot:subtitle>
    @endisset

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    {{ $slot }}
</x-layouts.dashboard>
