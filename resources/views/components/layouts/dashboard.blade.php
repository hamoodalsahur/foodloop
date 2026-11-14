{{--
    The seller and admin layout: forest sidebar on wide screens, stacked on top on phones.

    <x-layouts.dashboard title="Tonight" area="Seller" active="dashboard" :nav="[
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('seller.dashboard')],
        ['key' => 'listings', 'label' => 'Listings', 'href' => '#'],
    ]">
        <x-slot:identity>…shop name and status…</x-slot:identity>      (optional box under the logo)
        <x-slot:subtitle>Friday 13 Nov · pickup window opens at 7:00 PM</x-slot:subtitle>
        <x-slot:actions><x-button>New listing</x-button></x-slot:actions>
        …page content…
    </x-layouts.dashboard>
--}}
@props(['title', 'area' => 'Seller', 'nav' => [], 'active' => null])

<x-layouts.base :title="$title">
    <div class="min-h-screen lg:grid lg:grid-cols-[14rem_1fr]">
        <aside class="bg-forest text-cream lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:overflow-y-auto">
            <div class="flex items-baseline gap-2 px-5 pt-5 lg:px-6 lg:pt-6">
                <a href="{{ $nav[0]['href'] ?? '#' }}" class="inline-flex min-h-11 items-center font-display text-2xl font-bold tracking-tight">
                    foodloop<span class="text-marigold">.</span>
                </a>
                <span class="text-[0.6875rem] font-bold uppercase tracking-widest text-mist">{{ $area }}</span>
            </div>

            @isset($identity)
                <div class="mx-5 mt-4 rounded-2xl border border-cream/20 px-3 py-2.5 text-sm lg:mx-4">
                    {{ $identity }}
                </div>
            @endisset

            <nav aria-label="{{ $area }}" class="mt-4 lg:mt-6 lg:flex-1">
                {{-- Phones: one row you can swipe. Wide screens: a column. --}}
                <ul class="flex gap-2 overflow-x-auto px-5 pb-4 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-4 lg:pb-0">
                    @foreach ($nav as $item)
                        <li class="shrink-0">
                            <a href="{{ $item['href'] }}"
                               @if ($active === $item['key']) aria-current="page" @endif
                               @class([
                                   'flex min-h-11 items-center rounded-xl px-3 text-sm whitespace-nowrap',
                                   'bg-marigold font-bold text-forest' => $active === $item['key'],
                                   'font-medium text-cream hover:bg-cream/10' => $active !== $item['key'],
                               ])>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @auth
                <form method="POST" action="{{ route('logout') }}" class="hidden px-4 pb-5 lg:block">
                    @csrf
                    <p class="truncate px-3 text-xs text-mist">{{ auth()->user()->name }}</p>
                    <button type="submit" class="mt-1 flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-medium text-cream hover:bg-cream/10">
                        Log out
                    </button>
                </form>
            @endauth
        </aside>

        <main id="main" class="min-w-0 px-5 py-6 md:px-10 md:py-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-display text-4xl font-bold tracking-tight">{{ $title }}</h1>
                    @isset($subtitle)
                        <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                    @endisset
                </div>
                @isset($actions)
                    <div class="flex flex-wrap gap-3">{{ $actions }}</div>
                @endisset
            </div>

            <div class="mt-6">
                {{ $slot }}
            </div>

            @auth
                <form method="POST" action="{{ route('logout') }}" class="mt-10 lg:hidden">
                    @csrf
                    <x-button type="submit" variant="outline">Log out</x-button>
                </form>
            @endauth
        </main>
    </div>
</x-layouts.base>
