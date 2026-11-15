{{--
    The customer (student) layout: phone-first, with the bottom navigation.
    On wide screens the same four links move into the header.

    <x-layouts.customer title="Deals" active="deals">
        <x-slot:notice>12 deals close within the hour</x-slot:notice>   (optional dark strip)
        …page content…
    </x-layouts.customer>

    Options:
      active    deals | map | orders | impact   marks the current page in the menu
      :header="false"   no logo bar (detail pages that start with a picture or a dark block)
      :nav="false"      no bottom navigation (pages with their own button fixed at the bottom)
      flush             no side padding on the content (the page adds its own px-5)
      wide              a wider page on desktop (deals in two columns, listing side by side)

    Signed-in people get the bell (their notifications) and the account menu.
    It also shows flash messages (session "status"), and a red "You're offline" strip
    when the phone loses its connection.
--}}
@props(['title' => null, 'active' => null, 'header' => true, 'nav' => true, 'flush' => false, 'wide' => false])

@php
    // A menu item whose page doesn't exist yet has no link and is shown greyed out.
    $link = fn (string $route) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;

    // icon: the SVG path drawn above the label in the bottom navigation.
    $menu = [
        'deals' => ['label' => 'Deals', 'href' => $link('deals.index'), 'icon' => 'M4 7h16l-1.5 11.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5zM9 7V5.5a3 3 0 0 1 6 0V7'],
        'map' => ['label' => 'Map', 'href' => $link('map'), 'icon' => 'M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11zM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
        'orders' => ['label' => 'Orders', 'href' => $link('orders.index'), 'icon' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9.5 8h5M9.5 12h5'],
        'impact' => ['label' => 'Impact', 'href' => $link('impact'), 'icon' => 'M5 20V12M12 20V5M19 20v-6'],
    ];

    $user = auth()->user();
    $initials = $user
        ? str($user->name)->explode(' ')->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('')
        : null;
@endphp

<x-layouts.base :title="$title">
    <div @class(['mx-auto flex min-h-screen max-w-2xl flex-col', 'lg:max-w-5xl' => $wide, 'pb-20 md:pb-10' => $nav, 'md:pb-10' => ! $nav])>
        @if ($header)
            <header class="flex items-center justify-between gap-4 px-5 py-4">
                <a href="{{ $menu['deals']['href'] }}" class="inline-flex min-h-11 items-center font-display text-3xl font-bold tracking-tight">
                    foodloop<span class="text-marigold">.</span>
                </a>

                <nav aria-label="Main" class="hidden md:block">
                    <ul class="flex gap-1">
                        @foreach ($menu as $key => $item)
                            <li>
                                @if ($item['href'])
                                    <a href="{{ $item['href'] }}"
                                       @if ($active === $key) aria-current="page" @endif
                                       @class([
                                           'flex min-h-11 items-center rounded-full px-4 text-sm font-semibold',
                                           'bg-forest text-cream' => $active === $key,
                                           'text-forest hover:bg-line' => $active !== $key,
                                       ])>
                                        {{ $item['label'] }}
                                    </a>
                                @else
                                    <span class="flex min-h-11 items-center px-4 text-sm font-semibold text-muted/60" title="Coming soon">
                                        {{ $item['label'] }}<span class="sr-only"> (coming soon)</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                @if ($user)
                    <div class="flex items-center gap-2">
                    <x-bell />
                    {{-- The round initials button opens a small account menu. --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
                                class="flex size-11 items-center justify-center rounded-full border-2 border-forest bg-white text-sm font-bold">
                            <span aria-hidden="true">{{ $initials }}</span>
                            <span class="sr-only">Account menu for {{ $user->name }}</span>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity
                             class="absolute right-0 z-50 mt-2 w-56 rounded-2xl border-2 border-forest bg-white p-2 shadow-lg">
                            <p class="truncate px-3 pt-2 text-sm font-bold">{{ $user->name }}</p>
                            <p class="truncate px-3 pb-2 text-xs text-muted">{{ $user->email }}</p>
                            <a href="{{ route('notifications.index') }}" class="flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold hover:bg-cream">
                                Notifications and alerts
                            </a>
                            @unless ($user->isCustomer())
                                <a href="{{ $user->homeUrl() }}" class="flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold hover:bg-cream">
                                    {{ $user->role->label() }} area
                                </a>
                            @endunless
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-semibold hover:bg-cream">
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                    </div>
                @else
                    <x-button variant="outline" :href="route('login')">Log in</x-button>
                @endif
            </header>
        @endif

        {{-- Shown by the browser itself when the connection drops. --}}
        <div x-data="{ offline: ! navigator.onLine }" x-show="offline" x-cloak
             @offline.window="offline = true" @online.window="offline = false"
             role="status" class="bg-danger text-white">
            <p class="flex items-center gap-3 px-5 py-3 text-sm font-medium">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true">
                    <path d="M10 3 18.5 17h-17zM10 8.5v4M10 14.8v.2" />
                </svg>
                You’re offline
            </p>
        </div>

        @isset($notice)
            <div class="bg-forest text-cream md:mx-5 md:rounded-2xl">
                <p class="flex items-center gap-3 px-5 py-3 text-sm font-medium">
                    <span class="size-2 shrink-0 rounded-full bg-marigold" aria-hidden="true"></span>
                    {{ $notice }}
                </p>
            </div>
        @endisset

        <main id="main" @class(['flex flex-1 flex-col', 'px-5 pt-5' => ! $flush])>
            @if (session('status') && ! $flush)
                <x-notice variant="success" class="mb-4">{{ session('status') }}</x-notice>
            @endif

            {{ $slot }}
        </main>
    </div>

    @if ($nav)
        {{-- Bottom navigation: phones only --}}
        <nav aria-label="Main" class="fixed inset-x-0 bottom-0 z-40 bg-forest text-cream md:hidden">
            <ul class="mx-auto grid max-w-2xl grid-cols-4 pb-[env(safe-area-inset-bottom)]">
                @foreach ($menu as $key => $item)
                    <li>
                        @if ($item['href'])
                            <a href="{{ $item['href'] }}"
                               @if ($active === $key) aria-current="page" @endif
                               @class([
                                   'flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-bold uppercase tracking-widest',
                                   'text-cream' => $active === $key,
                                   'text-mist' => $active !== $key,
                               ])>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     @class(['size-6', 'text-marigold' => $active === $key]) aria-hidden="true">
                                    <path d="{{ $item['icon'] }}" />
                                </svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-bold uppercase tracking-widest text-mist/50" title="Coming soon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true">
                                    <path d="{{ $item['icon'] }}" />
                                </svg>
                                <span>{{ $item['label'] }}<span class="sr-only"> (coming soon)</span></span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</x-layouts.base>
