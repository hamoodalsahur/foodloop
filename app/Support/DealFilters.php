<?php

namespace App\Support;

use App\Enums\ListingCategory;
use App\Enums\ListingType;
use Illuminate\Http\Request;

/**
 * What the customer picked on the deals page, read from the address bar:
 *
 *   /?sort=cheapest&halal=0&category=bakery&type=bundle&within=5
 *
 * Anything missing or not recognised quietly falls back to the default, so
 * a mistyped link still shows deals instead of an error.
 */
final readonly class DealFilters
{
    public const SORTS = [
        'closing' => 'Closing soon',
        'nearest' => 'Nearest',
        'cheapest' => 'Cheapest',
        'new' => 'New',
    ];

    public function __construct(
        public string $sort = 'closing',
        public bool $halalOnly = true,
        public ?ListingCategory $category = null,
        public ?ListingType $type = null,
        /** Null means any distance. */
        public ?int $withinKm = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $within = $request->query('within', (string) config('foodloop.default_distance_km'));

        return new self(
            sort: array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'closing',
            halalOnly: $request->query('halal', '1') !== '0',
            category: ListingCategory::tryFrom((string) $request->query('category')),
            type: ListingType::tryFrom((string) $request->query('type')),
            withinKm: in_array((int) $within, config('foodloop.distance_choices_km'), true) ? (int) $within : null,
        );
    }

    /**
     * The address of the deals page with these filters, after changing some.
     * Defaults are left out to keep links short.
     *
     *     $filters->url(['sort' => 'cheapest'])
     *     $filters->url(['category' => null])      // clear the category
     *     $filters->url(['within' => 'any'])
     *
     * @param  array<string, string|int|null>  $changes
     */
    public function url(array $changes = []): string
    {
        $query = [
            'sort' => $this->sort,
            'halal' => $this->halalOnly ? '1' : '0',
            'category' => $this->category?->value,
            'type' => $this->type?->value,
            'within' => $this->withinKm ?? 'any',
            ...$changes,
        ];

        $defaults = ['sort' => 'closing', 'halal' => '1', 'within' => config('foodloop.default_distance_km')];

        $query = array_filter(
            $query,
            fn ($value, $key) => $value !== null && (string) $value !== (string) ($defaults[$key] ?? ''),
            ARRAY_FILTER_USE_BOTH,
        );

        return route('deals.index', $query);
    }

    /** "Within 2 km" or "Any distance". */
    public function distanceLabel(): string
    {
        return $this->withinKm === null ? 'Any distance' : "Within {$this->withinKm} km";
    }

    /** The next choice when the distance tile is tapped: 2 km → 5 km → any → 2 km. */
    public function nextDistance(): int|string
    {
        $choices = [...config('foodloop.distance_choices_km'), 'any'];
        $current = array_search($this->withinKm ?? 'any', $choices, true);

        return $choices[($current + 1) % count($choices)];
    }

    /** The next wider distance, or null when already at "any". Used by the empty state. */
    public function widerDistance(): int|string|null
    {
        if ($this->withinKm === null) {
            return null;
        }

        $wider = array_filter(config('foodloop.distance_choices_km'), fn (int $km) => $km > $this->withinKm);

        return $wider === [] ? 'any' : min($wider);
    }
}
