{{--
    The deals page (mockups c01, c05, c06). Everyone can open it.
    Data comes from App\Http\Controllers\DealController.
--}}
@php
    use App\Support\Distance;

    use App\Support\DayPart;
@endphp

<x-layouts.customer title="Deals" active="deals" wide>
    @unless ($failed)
        <x-slot:notice>
            @if ($closingSoon > 0)
                {{ $closingSoon }} {{ Str::plural('deal', $closingSoon) }} {{ $closingSoon === 1 ? 'closes' : 'close' }} within the hour
            @else
                Quiet right now · sellers usually post near closing time
            @endif
        </x-slot:notice>
    @endunless

    <h1 class="sr-only">Deals</h1>

    @if ($failed)
        {{-- The database didn't answer (mockup c06). --}}
        <x-error-state page
                       title="Couldn’t load deals."
                       message="Check your connection and try again. Your reservations and pickup codes are safe."
                       :retry-url="url()->full()">
            @auth
                @if (auth()->user()->isCustomer())
                    <x-button size="lg" variant="outline" :href="route('orders.index')">Show my pickup code</x-button>
                @endif
            @endauth
        </x-error-state>
    @else
        {{-- Sort tabs --}}
        <nav aria-label="Sort deals" class="-mx-5 overflow-x-auto px-5">
            <ul class="flex min-w-max gap-5 border-b border-line">
                @foreach ($filters::SORTS as $key => $label)
                    <li>
                        <a href="{{ $filters->url(['sort' => $key]) }}"
                           @if ($filters->sort === $key) aria-current="true" @endif
                           @class([
                               '-mb-px flex min-h-11 items-center border-b-[3px] font-display text-base',
                               'border-forest font-bold text-forest' => $filters->sort === $key,
                               'border-transparent font-medium text-muted hover:text-forest' => $filters->sort !== $key,
                           ])>
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($listings->isEmpty())
            {{-- Nothing to rescue with these filters (mockup c05). The buttons undo one filter each. --}}
            @php
                $alertsOn = (bool) auth()->user()?->deal_alerts;
            @endphp
            <x-empty-state page
                           title="All gone nearby."
                           :message="($filters->withinKm
                               ? 'Everything within '.$filters->withinKm.' km that matches has been rescued. Try a wider area, or '
                               : 'Everything that matches has been rescued. You can ')
                               .($alertsOn ? 'wait for a notification: your deal alerts are on.' : 'get a notification when the next deal drops.')">
                @if ($filters->widerDistance())
                    <x-button size="lg" :href="$filters->url(['within' => $filters->widerDistance()])">
                        {{ $filters->widerDistance() === 'any' ? 'Search any distance' : 'Search within '.$filters->widerDistance().' km' }}
                    </x-button>
                @endif
                @if ($filters->category || $filters->type)
                    <x-button size="lg" variant="outline" :href="$filters->url(['category' => null, 'type' => null])">Show all food</x-button>
                @endif
                @if ($filters->halalOnly)
                    <x-button size="lg" variant="outline" :href="$filters->url(['halal' => '0'])">Include food not marked halal</x-button>
                @endif
                {{-- "Turn on deal alerts" (mockup c05): customers only; guests log in first. --}}
                @guest
                    <x-button size="lg" variant="outline" :href="route('login')">Turn on deal alerts</x-button>
                @else
                    @if (auth()->user()->isCustomer())
                        <form method="POST" action="{{ route('deal-alerts.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="on" value="{{ $alertsOn ? 0 : 1 }}">
                            <x-button type="submit" size="lg" variant="outline">{{ $alertsOn ? 'Turn off deal alerts' : 'Turn on deal alerts' }}</x-button>
                        </form>
                    @endif
                @endguest
            </x-empty-state>
        @else
            {{-- The three tiles: bundles, halal filter, distance filter. One slim row, so the deals show sooner. --}}
            <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                <a href="{{ $filters->url(['type' => $filters->type?->value === 'bundle' ? null : 'bundle']) }}"
                   @if ($filters->type?->value === 'bundle') aria-current="true" @endif
                   @class([
                       'flex min-h-[4.75rem] flex-col justify-center rounded-2xl bg-marigold px-3 py-2.5 text-forest sm:px-4',
                       'ring-4 ring-forest ring-inset' => $filters->type?->value === 'bundle',
                   ])>
                    <span class="flex items-baseline gap-1.5">
                        <span class="font-display text-3xl font-bold leading-none tracking-tight">{{ $bundlesLeft }}</span>
                        <span class="text-xs font-bold leading-tight sm:text-sm">Surprise {{ Str::plural('Bundle', $bundlesLeft) }}</span>
                    </span>
                    <span class="mt-1 text-xs font-semibold">
                        @if ($filters->type?->value === 'bundle')
                            left {{ DayPart::word() }} · show all deals
                        @else
                            left {{ DayPart::word() }} <span aria-hidden="true">→</span><span class="sr-only">, show only bundles</span>
                        @endif
                    </span>
                </a>

                <a href="{{ $filters->url(['halal' => $filters->halalOnly ? '0' : '1']) }}"
                   class="flex min-h-[4.75rem] flex-col justify-center rounded-2xl bg-forest px-3 py-2.5 text-cream sm:px-4">
                    <span class="text-xs text-mist">Halal only</span>
                    <span class="mt-1">
                        <x-tag :variant="$filters->halalOnly ? 'marigold' : 'outline'" :class="$filters->halalOnly ? '' : 'border-mist text-mist'">
                            {{ $filters->halalOnly ? 'On' : 'Off' }}
                        </x-tag>
                    </span>
                    <span class="sr-only">Tap to turn {{ $filters->halalOnly ? 'off' : 'on' }}</span>
                </a>

                <a href="{{ $filters->url(['within' => $filters->nextDistance()]) }}"
                   class="flex min-h-[4.75rem] flex-col justify-center rounded-2xl border-2 border-forest bg-white px-3 py-2.5 text-forest sm:px-4">
                    <span class="text-xs text-muted">Distance</span>
                    <span class="mt-1 font-display text-sm font-bold leading-tight sm:text-base">{{ $filters->distanceLabel() }}</span>
                    <span class="sr-only">Tap to change</span>
                </a>
            </div>

        {{-- Category chips --}}
        <nav aria-label="Food category" class="-mx-5 mt-4 overflow-x-auto px-5">
            <ul class="flex min-w-max gap-2">
                @foreach ([null, ...$categories] as $category)
                    @php($selected = $filters->category === $category)
                    <li>
                        <a href="{{ $filters->url(['category' => $category?->value]) }}"
                           @if ($selected) aria-current="true" @endif
                           @class([
                               'flex min-h-11 items-center rounded-full border-2 px-4 text-sm font-semibold',
                               'border-forest bg-forest text-cream' => $selected,
                               'border-line bg-white text-forest hover:border-forest' => ! $selected,
                           ])>
                            {{ $category?->label() ?? 'All food' }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

            <ul class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach ($listings as $listing)
                    <li>
                        <x-deal-row :listing="$listing"
                                    :href="route('listings.show', $listing)"
                                    :distance="Distance::label($listing->distanceKm)" />
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <p class="mt-auto pb-4 pt-8 text-xs text-muted">
        FoodLoop is a student prototype. Payments are simulated, and the shops, prices and distances shown are demo data.
    </p>
</x-layouts.customer>
