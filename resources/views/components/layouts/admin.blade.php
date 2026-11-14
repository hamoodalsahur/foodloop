{{--
    The dashboard layout with the admin menu filled in. Every admin page starts with this:

    <x-layouts.admin title="Overview" active="overview">…</x-layouts.admin>

    `active` is one of: overview, sellers, reports, customers.
--}}
@props(['title', 'active' => null])

@php
    $link = fn (string $route) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#';

    $nav = [
        ['key' => 'overview', 'label' => 'Overview', 'href' => $link('admin.dashboard')],
        ['key' => 'sellers', 'label' => 'Sellers', 'href' => $link('admin.sellers.index')],
        ['key' => 'reports', 'label' => 'Reports', 'href' => $link('admin.reports.index')],
        ['key' => 'customers', 'label' => 'Customers', 'href' => $link('admin.customers.index')],
    ];
@endphp

<x-layouts.dashboard :title="$title" area="Admin" :nav="$nav" :active="$active">
    @isset($subtitle)
        <x-slot:subtitle>{{ $subtitle }}</x-slot:subtitle>
    @endisset

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    {{ $slot }}
</x-layouts.dashboard>
