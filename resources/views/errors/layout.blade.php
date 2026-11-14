{{--
    Shared by the error pages (404, 403, 419, 500, 503). Deliberately simple: it does not
    look at who is signed in or touch the database, so it still works when those are the problem.

    @include('errors.layout', ['code' => 404, 'title' => 'Page not found.', 'message' => '…'])
--}}
<x-layouts.base :title="$title">
    <div class="mx-auto flex min-h-screen max-w-md flex-col px-5 pb-10">
        <header class="py-4">
            <a href="/" class="inline-flex min-h-11 items-center font-display text-3xl font-bold tracking-tight">
                foodloop<span class="text-marigold">.</span>
            </a>
        </header>

        <main id="main" class="mt-10">
            <p class="font-mono text-sm font-bold text-muted">Error {{ $code }}</p>
            <h1 class="mt-2 font-display text-5xl font-bold leading-none tracking-tight">{{ $title }}</h1>
            <p class="mt-4 text-base text-muted">{{ $message }}</p>

            <div class="mt-6 flex flex-col gap-3">
                @if ($retry ?? false)
                    <x-button size="lg" x-data @click="window.location.reload()">Try again</x-button>
                    <x-button size="lg" variant="outline" href="/">Back to the deals</x-button>
                @else
                    <x-button size="lg" href="/" arrow>Back to the deals</x-button>
                @endif
            </div>
        </main>
    </div>
</x-layouts.base>
