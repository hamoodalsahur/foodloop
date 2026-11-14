{{--
    The customer (student) layout: phone-first, with the bottom navigation.
    On wide screens the same four links move into the header.

    <x-layouts.customer title="Deals" active="deals">
        <x-slot:notice>12 deals close within the hour</x-slot:notice>   (optional dark strip)
        …page content…
    </x-layouts.customer>

    `active` is one of: deals, map, orders, impact.
--}}
@props(['title' => null, 'active' => null])

@php
    // Links to pages that don't exist yet stay as "#" until their stage adds the route.
    $link = fn (string $route) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#';

    $nav = [
        'deals' => ['label' => 'Deals', 'href' => $link('home')],
        'map' => ['label' => 'Map', 'href' => $link('map')],
        'orders' => ['label' => 'Orders', 'href' => $link('orders.index')],
        'impact' => ['label' => 'Impact', 'href' => $link('impact')],
    ];

    $user = auth()->user();
    $initials = $user
        ? str($user->name)->explode(' ')->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('')
        : null;
@endphp

<x-layouts.base :title="$title">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col pb-20 md:pb-10">
        <header class="flex items-center justify-between gap-4 px-5 py-4">
            <a href="{{ $nav['deals']['href'] }}" class="inline-flex min-h-11 items-center font-display text-3xl font-bold tracking-tight">
                foodloop<span class="text-marigold">.</span>
            </a>

            <nav aria-label="Main" class="hidden md:block">
                <ul class="flex gap-1">
                    @foreach ($nav as $key => $item)
                        <li>
                            <a href="{{ $item['href'] }}"
                               @if ($active === $key) aria-current="page" @endif
                               @class([
                                   'flex min-h-11 items-center rounded-full px-4 text-sm font-semibold',
                                   'bg-forest text-cream' => $active === $key,
                                   'text-forest hover:bg-line' => $active !== $key,
                               ])>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if ($user)
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-forest bg-white text-sm font-bold"
                      title="{{ $user->name }}">
                    <span aria-hidden="true">{{ $initials }}</span>
                    <span class="sr-only">Signed in as {{ $user->name }}</span>
                </span>
            @else
                <x-button variant="outline" :href="$link('login')">Log in</x-button>
            @endif
        </header>

        @isset($notice)
            <div class="bg-forest text-cream md:mx-5 md:rounded-2xl">
                <p class="flex items-center gap-3 px-5 py-3 text-sm font-medium">
                    <span class="size-2 shrink-0 rounded-full bg-marigold" aria-hidden="true"></span>
                    {{ $notice }}
                </p>
            </div>
        @endisset

        <main id="main" class="flex-1 px-5 pt-5">
            {{ $slot }}
        </main>
    </div>

    {{-- Bottom navigation: phones only --}}
    <nav aria-label="Main" class="fixed inset-x-0 bottom-0 z-40 bg-forest text-cream md:hidden">
        <ul class="mx-auto grid max-w-2xl grid-cols-4">
            @foreach ($nav as $key => $item)
                <li>
                    <a href="{{ $item['href'] }}"
                       @if ($active === $key) aria-current="page" @endif
                       @class([
                           'flex h-16 flex-col items-center justify-center gap-1.5 text-xs font-bold uppercase tracking-widest',
                           'text-cream' => $active === $key,
                           'text-mist' => $active !== $key,
                       ])>
                        {{ $item['label'] }}
                        <span @class(['h-1 w-5 rounded-full', 'bg-marigold' => $active === $key]) aria-hidden="true"></span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</x-layouts.base>
