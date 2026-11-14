<?php

// The design-system components (resources/views/components, docs/UI_GUIDE.md).
// These check what each component puts on the page, not how it looks; the
// look is checked in a browser against the mockups.

use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Seller;
use App\Support\TimeLeft;

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

describe('x-button', function () {
    it('is a real button by default and never submits a form by accident', function () {
        $this->blade('<x-button>Save</x-button>')
            ->assertSee('<button type="button"', false)
            ->assertSee('Save')
            ->assertSee('min-h-11', false);
    });

    it('becomes a link when it has an href', function () {
        $this->blade('<x-button href="/deals" variant="outline">Back</x-button>')
            ->assertSee('<a href="/deals"', false)
            ->assertSee('border-forest', false)
            ->assertDontSee('<button', false);
    });

    it('has a marigold variant, a submit type and an arrow', function () {
        $this->blade('<x-button variant="marigold" type="submit" size="lg" arrow>Reserve</x-button>')
            ->assertSee('type="submit"', false)
            ->assertSee('bg-marigold', false)
            ->assertSee('min-h-14', false)
            ->assertSee('<svg', false);
    });

    it('passes extra attributes through', function () {
        $this->blade('<x-button disabled class="mt-4" data-test="x">Wait</x-button>')
            ->assertSee('disabled', false)
            ->assertSee('mt-4', false)
            ->assertSee('data-test="x"', false);
    });
});

it('renders x-card in each variant and as another element', function () {
    $this->blade('<x-card>Hello</x-card>')->assertSee('bg-white', false)->assertSee('Hello');
    $this->blade('<x-card variant="forest" as="section">Dark</x-card>')
        ->assertSee('<section', false)
        ->assertSee('bg-forest', false);
    $this->blade('<x-card variant="marigold">Bright</x-card>')->assertSee('bg-marigold', false);
    $this->blade('<x-card variant="outline">Quiet</x-card>')->assertSee('border-forest', false);
});

describe('x-price-box', function () {
    it('shows the price and the crossed-out original, written by the money helper', function () {
        $this->blade('<x-price-box :price-sen="450" :original-sen="900" />')
            ->assertSee('RM4.50')
            ->assertSee('<s>RM9</s>', false)
            ->assertSee('instead of');
    });

    it('leaves the original out when there is none or it is not higher', function () {
        $this->blade('<x-price-box :price-sen="800" />')->assertSee('RM8')->assertDontSee('<s>', false);
        $this->blade('<x-price-box :price-sen="800" :original-sen="800" />')->assertDontSee('<s>', false);
    });
});

describe('x-status-pill', function () {
    it('shows the word for each reservation status', function (ReservationStatus $status, string $word, string $colour) {
        $this->blade('<x-status-pill :status="$status" />', ['status' => $status])
            ->assertSee($word)
            ->assertSee($colour, false);
    })->with([
        [ReservationStatus::Reserved, 'Reserved', 'bg-marigold-soft'],
        [ReservationStatus::Collected, 'Collected', 'bg-forest'],
        [ReservationStatus::NoShow, 'No-show', 'text-danger'],
        [ReservationStatus::Cancelled, 'Cancelled', 'text-muted'],
    ]);

    it('also accepts the status as plain text', function () {
        $this->blade('<x-status-pill status="no_show" />')->assertSee('No-show');
    });
});

describe('x-deal-row', function () {
    beforeEach(function () {
        $this->listing = Listing::factory()
            ->for(Seller::factory()->approved()->state(['business_name' => 'Mak Long’s Kuih']))
            ->window(-60, 18)
            ->create(['title' => 'Curry puffs × 4', 'price_sen' => 300, 'original_price_sen' => 600]);
    });

    it('shows the time left, title, shop, distance and price, and links to the listing', function () {
        $this->blade('<x-deal-row :listing="$listing" href="/listings/7" distance="0.4 km" />', ['listing' => $this->listing])
            ->assertSee('<a href="/listings/7"', false)
            ->assertSee('18 min left')
            ->assertSee('Curry puffs × 4')
            ->assertSee('Mak Long’s Kuih')
            ->assertSee('0.4 km')
            ->assertSee('RM3')
            ->assertSee('<s>RM6</s>', false);
    });

    it('highlights the time only when the deal is about to close', function () {
        $soon = $this->blade('<x-deal-row :listing="$listing" />', ['listing' => $this->listing]);
        $soon->assertSee('rounded-md bg-marigold', false);

        $later = Listing::factory()->window(-60, 90)->create();
        $this->blade('<x-deal-row :listing="$listing" />', ['listing' => $later])
            ->assertSee('1h 30m left')
            ->assertDontSee('rounded-md bg-marigold', false);
    });

    it('says sold out instead of the time when nothing is left', function () {
        $this->listing->quantity_available = 0;

        $this->blade('<x-deal-row :listing="$listing" />', ['listing' => $this->listing])
            ->assertSee('Sold out')
            ->assertDontSee('min left');
    });
});

it('writes time left the way the deals list shows it', function (int $minutes, string $label, bool $urgent) {
    $until = now()->addMinutes($minutes);

    expect(TimeLeft::label($until))->toBe($label)
        ->and(TimeLeft::isUrgent($until))->toBe($urgent);
})->with([
    [18, '18 min left', true],
    [35, '35 min left', true],
    [36, '36 min left', false],
    [59, '59 min left', false],
    [60, '1h left', false],
    [162, '2h 42m left', false],
    [0, 'Ended', false],
    [-5, 'Ended', false],
]);

it('rounds a part-minute up, so a deal never says "0 min left" while it is open', function () {
    expect(TimeLeft::label(now()->addSeconds(20)))->toBe('1 min left');
});

it('renders x-stat-tile with its label, value and hint', function () {
    $this->blade('<x-stat-tile label="Rescued this month" value="64" variant="forest" hint="meals" />')
        ->assertSee('Rescued this month')
        ->assertSee('64')
        ->assertSee('meals')
        ->assertSee('bg-forest', false);
});

it('renders x-empty-state with a title, a message and an action', function () {
    $this->blade('<x-empty-state title="No deals nearby" message="Check back after 5 PM."><x-button href="/">Show all</x-button></x-empty-state>')
        ->assertSee('No deals nearby')
        ->assertSee('Check back after 5 PM.')
        ->assertSee('Show all');
});

describe('x-error-state', function () {
    it('always offers "Try again" and announces itself to screen readers', function () {
        $this->blade('<x-error-state />')
            ->assertSee('Something went wrong')
            ->assertSee('Try again')
            ->assertSee('role="alert"', false)
            ->assertSee('window.location.reload()', false);
    });

    it('can send "Try again" to a URL and take its own words', function () {
        $this->blade('<x-error-state title="We couldn’t load your orders" message="Check your connection." retry-url="/orders" />')
            ->assertSee('We couldn’t load your orders')
            ->assertSee('Check your connection.')
            ->assertSee('<a href="/orders"', false);
    });
});

it('renders x-spec-grid as label and value pairs', function () {
    $this->blade('<x-spec-grid :items="$items" />', ['items' => ['Pickup' => '7–9 PM', 'Left' => '3 of 5', 'Halal' => 'Halal certified']])
        ->assertSeeInOrder(['Pickup', '7–9 PM', 'Left', '3 of 5', 'Halal', 'Halal certified'])
        ->assertSee('<dl', false)
        ->assertSee('col-span-2', false);
});

describe('x-food-photo', function () {
    it('shows the picture from the public storage folder with its description', function () {
        $this->blade('<x-food-photo path="listings/demo/curry-puffs.svg" alt="Curry puffs" />')
            ->assertSee('/storage/listings/demo/curry-puffs.svg', false)
            ->assertSee('alt="Curry puffs"', false)
            ->assertSee('loading="lazy"', false);
    });

    it('shows a placeholder when there is no picture', function () {
        $this->blade('<x-food-photo :path="null" alt="No photo yet" />')
            ->assertDontSee('<img', false)
            ->assertSee('aria-label="No photo yet"', false);
    });
});

it('renders x-tag', function () {
    $this->blade('<x-tag variant="marigold">On</x-tag>')->assertSee('On')->assertSee('bg-marigold', false);
});

it('uses only theme colour names in views, never raw hex codes', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => str_contains($file->getPathname(), 'dev'.DIRECTORY_SEPARATOR.'components'))
        ->filter(fn ($file) => preg_match('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?![0-9a-zA-Z_-])/', preg_replace('/href="#[^"]*"/', '', $file->getContents())))
        ->map->getRelativePathname()
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
