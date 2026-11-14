<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · surplus food, student prices</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream font-sans text-forest antialiased">
    {{-- Temporary home page for Stage 0. The real screens (deals, listing, orders…)
         replace this from Stage 3 onwards, using the mockups in docs/mockups. --}}

    <header class="mx-auto flex max-w-5xl items-center justify-between px-5 py-5">
        <a href="{{ route('home') }}" class="font-display text-3xl font-bold tracking-tight">
            foodloop<span class="text-marigold">.</span>
        </a>
        <span class="rounded-full border-2 border-forest px-3 py-1 text-xs font-semibold uppercase tracking-wide">
            Prototype
        </span>
    </header>

    <div class="bg-forest text-cream">
        <p class="mx-auto flex max-w-5xl items-center gap-3 px-5 py-3 text-sm font-medium">
            <span class="size-2 shrink-0 rounded-full bg-marigold"></span>
            Setup is done. The app screens are being built next.
        </p>
    </div>

    <main class="mx-auto max-w-5xl px-5 pb-16 pt-8">
        <div class="grid gap-4 md:grid-cols-5">
            <section class="flex flex-col justify-between rounded-3xl bg-marigold p-6 md:col-span-3 md:p-10">
                <p class="text-sm font-semibold">Surplus food, student prices</p>
                <h1 class="mt-16 font-display text-5xl font-bold leading-none tracking-tight md:mt-24 md:text-7xl">
                    Good food shouldn't go to waste.
                </h1>
                <p class="mt-5 max-w-md text-base font-medium">
                    Campus sellers post what's left at a discount. You reserve it, then collect it yourself
                    in the pickup window.
                </p>
            </section>

            <section class="rounded-3xl bg-forest p-6 text-cream md:col-span-2 md:p-8"
                     x-data="{ step: 1 }">
                <p class="text-sm text-cream/70">How it works</p>

                <div class="mt-4 flex gap-2" role="tablist" aria-label="How it works">
                    @foreach ([1 => 'Browse', 2 => 'Reserve', 3 => 'Collect'] as $number => $label)
                        <button type="button" role="tab"
                                @click="step = {{ $number }}"
                                :aria-selected="step === {{ $number }}"
                                :class="step === {{ $number }} ? 'bg-marigold text-forest' : 'bg-cream/10 text-cream'"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition-colors {{ $number === 1 ? 'bg-marigold text-forest' : 'bg-cream/10 text-cream' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-8 min-h-40">
                    <div x-show="step === 1">
                        <p class="font-display text-6xl font-bold text-marigold">1</p>
                        <p class="mt-3 font-display text-xl font-bold">Find a deal nearby</p>
                        <p class="mt-2 text-sm text-cream/80">
                            See what campus sellers have left, with the halal status and pickup window on every listing.
                        </p>
                    </div>
                    <div x-show="step === 2" x-cloak>
                        <p class="font-display text-6xl font-bold text-marigold">2</p>
                        <p class="mt-3 font-display text-xl font-bold">Reserve up to {{ config('foodloop.max_qty_per_listing') }} portions</p>
                        <p class="mt-2 text-sm text-cream/80">
                            Pay now or pay at pickup. Cancel for free until
                            {{ config('foodloop.free_cancel_minutes') }} minutes before the window starts.
                        </p>
                    </div>
                    <div x-show="step === 3" x-cloak>
                        <p class="font-display text-6xl font-bold text-marigold">3</p>
                        <p class="mt-3 font-display text-xl font-bold">Show your 4-digit code</p>
                        <p class="mt-2 text-sm text-cream/80">
                            Collect in the pickup window. Reservations not collected
                            {{ config('foodloop.grace_minutes') }} minutes after it ends count as a no-show.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <p class="mt-8 text-sm text-forest/70">
            FoodLoop is a student prototype for the ENT600 Technology Entrepreneurship course.
            Payments are simulated and any shops, prices or numbers shown in the app are demo data.
        </p>
    </main>
</body>
</html>
