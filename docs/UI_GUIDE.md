# FoodLoop · UI guide

How to build a FoodLoop screen so it matches the approved mockups (style C "Bold", `docs/mockups/`).
Read this before making any page. Every example below is live at **`/dev/components`** when the app runs
locally (`php artisan serve`, then open <http://localhost:8000/dev/components>). The two layouts are at
`/dev/layouts/customer` and `/dev/layouts/dashboard`. Those pages use sample data and only exist when
`APP_ENV=local`.

## The 8 rules

1. **Use the components.** If a button, card, price, pill, tile or empty/error box already exists here,
   use it. Don't rebuild one with raw classes. Need something new? Add a component and add it to
   `/dev/components` and this file.
2. **Colours by name, never by hex code.** `bg-forest`, `text-muted`, `border-line`… A test fails if a
   view contains a hex colour.
3. **Money goes through the helper.** Prices are stored as whole sen. Show them with
   `App\Support\Money::format($sen)` or the model's `price_formatted`. Never write `RM` by hand or divide by 100
   in a view. Result: `RM8`, `RM4.50`.
4. **44 px touch targets.** Anything you can tap is at least 44 px tall (`min-h-11`). `x-button` already is.
5. **Readable contrast.** Use only the text/background pairs in the table below.
6. **Colour is never the only signal.** A status always has its word ("No-show"), not just a red dot.
7. **No emoji.** Use words, or a small inline SVG icon with `aria-hidden="true"`.
8. **Phone first.** Build for a 390 px wide screen, then add `md:` / `lg:` classes for wider ones. Check both
   widths in a browser before calling a page done. Nothing may scroll sideways.

## Colours

Defined once in `resources/css/app.css` (`@theme`). Each name works with every Tailwind colour utility
(`bg-`, `text-`, `border-`…).

| Name | Hex | Use it for |
|---|---|---|
| `forest` | `#0E2A20` | Text, dark panels, primary buttons |
| `marigold` | `#FFB800` | The one highlight on a screen: urgency, call-to-action tiles, the active menu item |
| `cream` | `#FAF6EE` | Page background |
| `white` | `#FFFFFF` | Cards |
| `muted` | `#55605A` | Secondary text on light backgrounds |
| `line` | `#E6DECC` | Hairlines, quiet borders, placeholders |
| `mist` | `#A8C2B6` | Secondary text on forest |
| `danger` | `#B42318` | Errors and no-shows |
| `marigold-soft` | `#FFE7A3` | "Reserved" pill |
| `danger-soft` | `#FDECEA` | "No-show" pill, error panels |

**Allowed text on background pairs** (WCAG contrast ratio; 4.5 is the minimum for normal text):

| Text | On | Ratio |
|---|---|---|
| `forest` | `cream` / `white` / `marigold` / `marigold-soft` / `danger-soft` | 14.2 / 15.3 / 8.8 / 12.6 / 13.4 |
| `cream` or `white` | `forest` | 14.2 |
| `muted` | `cream` / `white` / `line` | 6.1 / 6.5 / 4.9 |
| `mist` | `forest` | 8.1 |
| `marigold` | `forest` | 8.8 |
| `danger` | `cream` / `danger-soft` | 6.1 / 5.7 |

**Never:** `marigold` text on a light background (1.6), `muted` text on `marigold` (3.8), `mist` on anything
but `forest`.

## Type

| Class | Font | Use it for |
|---|---|---|
| `font-display` | Space Grotesk, bold | Headings, prices, big numbers, deal titles |
| `font-sans` (default) | Inter | Everything else |

Both fonts are bundled with the app (no internet needed). Page title: `font-display text-4xl font-bold
tracking-tight`. Card heading: `font-display text-2xl font-bold`. Small labels above a value:
`text-[0.6875rem] font-bold uppercase tracking-wider text-muted`.

## Layouts

Every page starts with one of these. They add the HTML shell, fonts, CSS/JS, navigation and a
"Skip to content" link.

### Customer pages: `<x-layouts.customer>`

Phone-first, with the bottom navigation (Deals, Map, Orders, Impact). On wide screens the same links move
into the header.

```blade
<x-layouts.customer title="Deals" active="deals">
    <x-slot:notice>12 deals close within the hour</x-slot:notice>

    <h1 class="font-display text-4xl font-bold tracking-tight">Deals</h1>
    …
</x-layouts.customer>
```

- `active`: `deals`, `map`, `orders` or `impact` (marks the current page).
- `notice` slot: optional dark strip under the header.
- A menu link stays `#` until its page exists; the layout looks routes up by name (`home`, `map`,
  `orders.index`, `impact`, `login`).

### Seller pages: `<x-layouts.seller>` · Admin pages: `<x-layouts.admin>`

Forest sidebar on wide screens; on phones it stacks on top and the menu becomes one row you can swipe.

```blade
<x-layouts.seller title="Tonight" active="dashboard">
    <x-slot:subtitle>Friday 13 Nov · pickup window opens at 7:00 PM</x-slot:subtitle>
    <x-slot:actions>
        <x-button>New listing</x-button>
    </x-slot:actions>

    …
</x-layouts.seller>
```

- Seller `active`: `dashboard`, `listings`, `pickups`, `donations`, `profile`.
  Admin `active`: `overview`, `sellers`, `listings`, `reports`, `customers`.
- The seller layout shows the signed-in seller's shop name and status by itself.
- Both are thin wrappers around `<x-layouts.dashboard>` (which takes `area`, `nav`, `active`).

Put the closing tag of a slot on its own line or follow it with a space: `</x-slot:notice>Text` on one
line confuses Blade.

## Components

All in `resources/views/components/`. Each file starts with a comment showing its options.

### `x-button`

```blade
<x-button>Save</x-button>
<x-button variant="marigold" type="submit">Check</x-button>
<x-button variant="outline" href="{{ route('home') }}">Back</x-button>
<x-button size="lg" arrow>Reserve for {{ $listing->price_formatted }}</x-button>
```

`variant`: `primary` (forest, default), `marigold`, `outline`. `size="lg"` is full width for the main action
of a page. `arrow` adds the round arrow badge. With `href` it renders a link. The default `type` is
`button`, so write `type="submit"` in forms. One primary or marigold action per card.

### `x-card`

```blade
<x-card>…</x-card>
<x-card variant="forest" as="section" padding="lg">…</x-card>
```

`variant`: `white` (default), `outline`, `marigold`, `forest`. `padding`: `md` (default), `lg`, `none`.
On forest use `text-mist` for small secondary text.

### `x-price-box`

```blade
<x-price-box :price-sen="$listing->price_sen" :original-sen="$listing->original_price_sen" />
```

The dark price block. Takes **sen** and formats them itself. The crossed-out original only shows when it is
higher than the price.

### `x-deal-row`

```blade
<x-deal-row :listing="$listing" :href="route('listings.show', $listing)" distance="0.4 km" />
```

One deal in a list; the whole row is a link. Load the seller with the listings
(`Listing::with('seller')`). "18 min left" counts down to the end of the pickup window and turns marigold
at 35 minutes or less (`App\Support\TimeLeft`). With nothing left it says "Sold out".

### `x-status-pill`

```blade
<x-status-pill :status="$reservation->status" />
```

`reserved` (soft marigold), `collected` (forest), `no_show` (soft red), `cancelled` (grey). Takes the
`ReservationStatus` enum or its text value.

### `x-tag`

```blade
<x-tag>Surprise Bundle</x-tag>
<x-tag variant="marigold">On</x-tag>
```

Small uppercase label for deal types, "Sold out", on/off. `variant`: `forest` (default), `marigold`,
`outline`.

### `x-stat-tile`

```blade
<x-stat-tile label="Waiting for pickup" value="3" variant="marigold" />
<x-stat-tile label="Sales this week" :value="Money::format($sen)" />
<x-stat-tile label="Rescued this month" value="64" variant="forest" hint="meals" />
```

`variant`: `outline` (default), `marigold`, `forest`. Use one marigold tile per row, for the number that
needs action.

### `x-spec-grid`

```blade
<x-spec-grid :items="[
    'Pickup' => '7–9 PM',
    'Left' => $listing->quantity_available.' of '.$listing->quantity_total,
    'Halal' => $listing->halal_status->label(),
    'Contains' => 'Egg, milk, wheat',
]" />
```

The two-column box of facts on a listing. An odd last item spans the full width.

### `x-food-photo`

```blade
<x-food-photo :path="$listing->photo_path" :alt="$listing->title" />
<x-food-photo :path="$listing->photo_path" :alt="$listing->title" size="hero" />
```

`size`: `sm` (48 px), `md` (80 px, default), `hero` (full width). Without a path it shows a plate
placeholder. Give `alt=""` when the title is written right next to the picture.

### `x-empty-state`

```blade
<x-empty-state title="No deals nearby right now"
               message="Sellers usually post their leftovers after 5 PM.">
    <x-button variant="outline" href="…">Search within 5 km</x-button>
</x-empty-state>
```

For any list with nothing in it. Say what is empty, why, and offer one next step.

### `x-error-state`

```blade
<x-error-state />
<x-error-state title="We couldn’t load your orders" message="Check your connection and try again."
               :retry-url="route('orders.index')" />
```

For anything that failed to load. Always has **Try again** (reloads the page, or goes to `retry-url`).

## Patterns

- **Grids:** always write the phone column count too: `grid grid-cols-1 gap-3 md:grid-cols-2`. A grid without
  `grid-cols-1` can push long text off the screen.
- **Tables:** a real `<table>` from `md:` up, a stack of small cards below that
  (see `resources/views/dev/layout-dashboard.blade.php`).
- **Forms:** inputs are `min-h-11 rounded-xl border-2 border-forest bg-cream px-3`, each with a `<label>`.
- **Refusals:** when the reservation engine says no, show `$exception->reason->label()`; the sentences are
  written in `App\Enums\RefusalReason`.
- **Who sees what:** wrap role areas in `Route::middleware(['auth', 'role:seller'])` and check single
  records with the policies: `Gate::authorize('update', $listing)` in controllers, `@can('publish', $listing)`
  in views.
- **Words:** plain and short. "Sold out. Someone just took the last one." beats "Insufficient inventory".
- **Demo data** is always called demo or sample data on the page that shows it.

## Before you call a screen done

- [ ] Matches its mockup in `docs/mockups/` (compare side by side).
- [ ] Looked at in a browser at 390 px and at 1280 px: nothing scrolls sideways, nothing overlaps.
- [ ] Empty and error cases use `x-empty-state` / `x-error-state`.
- [ ] Every tappable thing is at least 44 px tall; keyboard focus is visible on all of them.
- [ ] Prices via the money helper, colours by name, no emoji.
- [ ] A test covers what the page shows and who may open it.
