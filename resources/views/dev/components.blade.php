{{--
    /dev/components · every design-system component on one page (local only).
    The rules and copy-paste examples are in docs/UI_GUIDE.md.
--}}
@php
    use App\Support\Money;

    $section = 'scroll-mt-6 border-t-2 border-line pt-8';
    $name = 'font-mono text-sm font-bold text-muted';
@endphp

<x-layouts.base title="Components">
    <main id="main" class="mx-auto max-w-5xl space-y-10 px-5 py-8">
        <header>
            <p class="font-display text-3xl font-bold tracking-tight">foodloop<span class="text-marigold">.</span></p>
            <h1 class="mt-6 font-display text-5xl font-bold tracking-tight">Components</h1>
            <p class="mt-3 max-w-2xl text-muted">
                Every building block of the FoodLoop screens, in style C. All shops, prices and names on this page
                are <strong class="text-forest">sample data</strong> for the design preview.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-button variant="outline" :href="route('dev.layouts.customer')">Customer layout</x-button>
                <x-button variant="outline" :href="route('dev.layouts.dashboard')">Dashboard layout</x-button>
            </div>
        </header>

        <section id="colours" class="{{ $section }}">
            <h2 class="font-display text-2xl font-bold">Colours</h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    ['forest', '#0E2A20', 'bg-forest', 'Text, dark panels'],
                    ['marigold', '#FFB800', 'bg-marigold', 'Highlights, urgency'],
                    ['cream', '#FAF6EE', 'bg-cream', 'Page background'],
                    ['muted', '#55605A', 'bg-muted', 'Secondary text'],
                    ['line', '#E6DECC', 'bg-line', 'Borders'],
                    ['mist', '#A8C2B6', 'bg-mist', 'Secondary text on forest'],
                    ['danger', '#B42318', 'bg-danger', 'Errors, no-shows'],
                    ['marigold-soft', '#FFE7A3', 'bg-marigold-soft', 'Reserved pill'],
                    ['danger-soft', '#FDECEA', 'bg-danger-soft', 'Error panels'],
                    ['white', '#FFFFFF', 'bg-white', 'Cards'],
                ] as [$colour, $hex, $class, $use])
                    <li class="overflow-hidden rounded-2xl border-2 border-line bg-white">
                        <div class="h-16 {{ $class }}"></div>
                        <div class="px-3 py-2">
                            <p class="font-mono text-sm font-bold">{{ $colour }}</p>
                            <p class="text-xs text-muted">{{ $hex }} · {{ $use }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section id="type" class="{{ $section }}">
            <h2 class="font-display text-2xl font-bold">Type</h2>
            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <p class="{{ $name }}">font-display · Space Grotesk</p>
                    <p class="mt-2 font-display text-5xl font-bold leading-none tracking-tight">−60% tonight</p>
                    <p class="mt-3 font-display text-2xl font-bold">Bakery Surprise Bundle</p>
                </div>
                <div>
                    <p class="{{ $name }}">font-sans · Inter</p>
                    <p class="mt-2 text-base">4–6 of today’s unsold breads, buns and pastries. You’ll find out what’s inside at pickup.</p>
                    <p class="mt-2 text-sm text-muted">Roti Lane Bakery · 0.6 km · verified seller</p>
                </div>
            </div>
        </section>

        <section id="button" class="{{ $section }}">
            <h2 class="{{ $name }}">x-button</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-button>Primary</x-button>
                <x-button variant="marigold">Marigold</x-button>
                <x-button variant="outline">Outline</x-button>
                <x-button href="#button">Link as button</x-button>
                <x-button disabled>Disabled</x-button>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-button size="lg" arrow>Reserve for {{ Money::format(800) }}</x-button>
                <x-button size="lg" variant="marigold" arrow>Offer to food bank</x-button>
            </div>
        </section>

        <section id="card" class="{{ $section }}">
            <h2 class="{{ $name }}">x-card</h2>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-card><p class="font-display text-lg font-bold">White</p><p class="mt-1 text-sm text-muted">The default panel.</p></x-card>
                <x-card variant="outline"><p class="font-display text-lg font-bold">Outline</p><p class="mt-1 text-sm text-muted">For filters and quiet stats.</p></x-card>
                <x-card variant="marigold"><p class="font-display text-lg font-bold">Marigold</p><p class="mt-1 text-sm font-medium">One highlight per screen.</p></x-card>
                <x-card variant="forest"><p class="font-display text-lg font-bold">Forest</p><p class="mt-1 text-sm text-mist">Dark panel, mist for small text.</p></x-card>
            </div>
        </section>

        <section id="tag" class="{{ $section }}">
            <h2 class="{{ $name }}">x-tag</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-tag>Surprise Bundle</x-tag>
                <x-tag>Sold out</x-tag>
                <x-tag variant="marigold">On</x-tag>
                <x-tag variant="outline">Flash Deal</x-tag>
            </div>
        </section>

        <section id="status-pill" class="{{ $section }}">
            <h2 class="{{ $name }}">x-status-pill</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                @foreach ($statuses as $status)
                    <x-status-pill :status="$status" />
                @endforeach
            </div>
        </section>

        <section id="price-box" class="{{ $section }}">
            <h2 class="{{ $name }}">x-price-box</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-price-box :price-sen="800" :original-sen="2000" />
                <x-price-box :price-sen="450" :original-sen="900" />
                <x-price-box :price-sen="650" />
            </div>
        </section>

        <section id="food-photo" class="{{ $section }}">
            <h2 class="{{ $name }}">x-food-photo</h2>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <x-food-photo :path="$listings['bundle']->photo_path" alt="Bakery Surprise Bundle" size="sm" />
                <x-food-photo :path="$listings['bundle']->photo_path" alt="Bakery Surprise Bundle" />
                <x-food-photo :path="null" alt="No photo yet" />
                <div class="w-full max-w-sm overflow-hidden rounded-3xl">
                    <x-food-photo :path="$listings['bundle']->photo_path" alt="Bakery Surprise Bundle" size="hero" />
                </div>
            </div>
        </section>

        <section id="deal-row" class="{{ $section }}">
            <h2 class="{{ $name }}">x-deal-row</h2>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-deal-row :listing="$listings['puffs']" href="#deal-row" distance="0.4 km" />
                <x-deal-row :listing="$listings['latte']" href="#deal-row" distance="0.9 km" />
                <x-deal-row :listing="$listings['bundle']" href="#deal-row" distance="0.6 km" />
                <x-deal-row :listing="$listings['nasiayam']" href="#deal-row" distance="0.3 km" />
                <x-deal-row :listing="$listings['croissants']" href="#deal-row" />
            </div>
        </section>

        <section id="stat-tile" class="{{ $section }}">
            <h2 class="{{ $name }}">x-stat-tile</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-stat-tile label="Waiting for pickup" value="3" variant="marigold" />
                <x-stat-tile label="Reservations today" value="9" />
                <x-stat-tile label="Sales this week" :value="Money::format(31200)" />
                <x-stat-tile label="Rescued this month" value="64" variant="forest" hint="meals" />
            </div>
        </section>

        <section id="spec-grid" class="{{ $section }}">
            <h2 class="{{ $name }}">x-spec-grid</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-spec-grid :items="[
                    'Pickup' => '7–9 PM',
                    'Left' => '3 of 5',
                    'Halal' => $listings['bundle']->halal_status->label(),
                    'Contains' => 'Egg, milk, wheat',
                ]" />
                <x-spec-grid :items="[
                    'Pickup' => '5–6 PM',
                    'Left' => '0 of 4',
                    'Pickup code' => '4721',
                ]" />
            </div>
        </section>

        <section id="empty-state" class="{{ $section }}">
            <h2 class="{{ $name }}">x-empty-state</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-empty-state title="No deals nearby right now"
                               message="Sellers usually post their leftovers after 5 PM. Check back then, or look a bit further away.">
                    <x-button variant="outline" href="#empty-state">Search within 5 km</x-button>
                </x-empty-state>
                <x-empty-state title="No pickups yet today" />
            </div>
        </section>

        <section id="error-state" class="{{ $section }}">
            <h2 class="{{ $name }}">x-error-state</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-error-state />
                <x-error-state title="We couldn’t load your orders"
                               message="Check your connection and try again. Your reservations are safe."
                               retry-url="#error-state" />
            </div>
        </section>
    </main>
</x-layouts.base>
