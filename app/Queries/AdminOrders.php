<?php

namespace App\Queries;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every order on FoodLoop, across all shops, for the admin Orders page
 * (the audit view). Read-only; nothing here changes data.
 *
 * One tab and one search box decide which orders are meant. The list, the
 * totals and the numbers on the tabs all come from the same filter, so they
 * always agree.
 */
class AdminOrders
{
    /** How many orders one page of the list shows. */
    public const PER_PAGE = 25;

    /**
     * The tabs, in the order they are shown. `all` has no status of its own.
     *
     * @var array<string, array{label: string, status: ReservationStatus|null}>
     */
    public const TABS = [
        'waiting' => ['label' => 'Waiting', 'status' => ReservationStatus::Reserved],
        'collected' => ['label' => 'Collected', 'status' => ReservationStatus::Collected],
        'cancelled' => ['label' => 'Cancelled', 'status' => ReservationStatus::Cancelled],
        'no-shows' => ['label' => 'No-shows', 'status' => ReservationStatus::NoShow],
        'all' => ['label' => 'All', 'status' => null],
    ];

    /** The tab a new visit opens on. */
    public const DEFAULT_TAB = 'waiting';

    private string $tab;

    private string $search;

    public function __construct(?string $tab = null, ?string $search = null)
    {
        $this->tab = array_key_exists((string) $tab, self::TABS) ? (string) $tab : self::DEFAULT_TAB;
        // Long pastes are cut off; nobody searches for more than a name or an email.
        $this->search = mb_substr(trim((string) $search), 0, 100);
    }

    public function tab(): string
    {
        return $this->tab;
    }

    public function search(): string
    {
        return $this->search;
    }

    /**
     * One page of orders, newest first, with everything a row shows already
     * loaded (item, shop, customer, the admin behind an override).
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function page(): LengthAwarePaginator
    {
        return $this->filtered($this->tab)
            ->with(['listing:id,seller_id,title', 'listing.seller:id,business_name', 'customer:id,name,email', 'collectedByAdmin:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Added up over every order in this tab and search, not just the page shown.
     *
     * @return array{orders: int, sen: int, portions: int}
     */
    public function totals(): array
    {
        $sums = $this->filtered($this->tab)
            ->toBase()
            ->selectRaw('count(*) as orders, coalesce(sum(total_sen), 0) as sen, coalesce(sum(quantity), 0) as portions')
            ->first();

        return [
            'orders' => (int) $sums->orders,
            'sen' => (int) $sums->sen,
            'portions' => (int) $sums->portions,
        ];
    }

    /**
     * How many orders each tab holds for this search, e.g. ['waiting' => 3, …, 'all' => 22].
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $byStatus = $this->filtered('all')
            ->toBase()
            ->selectRaw('status, count(*) as orders')
            ->groupBy('status')
            ->pluck('orders', 'status');

        $counts = [];

        foreach (self::TABS as $key => $tab) {
            $counts[$key] = (int) ($tab['status'] === null ? $byStatus->sum() : $byStatus->get($tab['status']->value, 0));
        }

        return $counts;
    }

    /** @return Builder<Reservation> */
    private function filtered(string $tab): Builder
    {
        $status = self::TABS[$tab]['status'];

        return Reservation::query()
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($this->search !== '', function (Builder $query) {
                // "%" and "_" typed in the box are looked for as they are, not as wildcards.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%';
                $matches = fn (Builder $q, string $column) => $q->whereRaw($column." like ? escape '!'", [$like]);

                $query->where(fn (Builder $any) => $any
                    ->where('pickup_code', $this->search)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where(fn (Builder $q) => $matches($q, 'name')->orWhere(fn (Builder $email) => $matches($email, 'email'))))
                    ->orWhereHas('listing.seller', fn (Builder $seller) => $matches($seller, 'business_name')));
            });
    }
}
