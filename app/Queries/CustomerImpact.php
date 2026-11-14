<?php

namespace App\Queries;

use App\Enums\DonationStatus;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The numbers on a customer's impact page (mockup c04). Everything is
 * worked out from COLLECTED reservations only: food that was reserved but
 * cancelled or never picked up was not rescued and does not count.
 */
class CustomerImpact
{
    /** @var Collection<int, Reservation>|null */
    private ?Collection $collected = null;

    public function __construct(private User $customer) {}

    /**
     * The customer's collected reservations, newest pickup first.
     *
     * @return Collection<int, Reservation>
     */
    public function collected(): Collection
    {
        return $this->collected ??= $this->customer->reservations()
            ->where('status', ReservationStatus::Collected)
            ->with('listing.seller')
            ->latest('collected_at')
            ->latest('id')
            ->get();
    }

    /** Portions picked up, ever. */
    public function mealsRescued(): int
    {
        return (int) $this->collected()->sum('quantity');
    }

    /** What the customer paid less than the normal prices, in sen. */
    public function savedSen(): int
    {
        return (int) $this->collected()->sum(fn (Reservation $reservation) => self::savingSen($reservation));
    }

    /** What one reservation saved: (normal price − paid price) × portions. Never negative. */
    public static function savingSen(Reservation $reservation): int
    {
        return max(0, $reservation->listing->saving_sen) * $reservation->quantity;
    }

    /**
     * Portions picked up in each of the last $count calendar weeks (Monday
     * to Sunday), oldest first. The last one is the current week.
     *
     * @return Collection<int, array{label: string, dates: string, meals: int, current: bool}>
     */
    public function weeks(int $count = 6): Collection
    {
        $thisWeek = now()->startOfWeek();

        return collect(range($count - 1, 0))->map(function (int $weeksAgo, int $index) use ($thisWeek) {
            $start = $thisWeek->copy()->subWeeks($weeksAgo);
            $end = $start->copy()->addWeek();

            return [
                'label' => 'W'.($index + 1),
                // "9–15 Nov" or "26 Oct–1 Nov"
                'dates' => $start->isSameMonth($end->copy()->subDay())
                    ? $start->format('j').'–'.$end->copy()->subDay()->format('j M')
                    : $start->format('j M').'–'.$end->copy()->subDay()->format('j M'),
                'meals' => (int) $this->collected()
                    ->filter(fn (Reservation $r) => $r->collected_at->greaterThanOrEqualTo($start) && $r->collected_at->lessThan($end))
                    ->sum('quantity'),
                'current' => $weeksAgo === 0,
            ];
        });
    }

    /**
     * The latest pickups, for the "Recent" list.
     *
     * @return Collection<int, Reservation>
     */
    public function recent(int $limit = 5): Collection
    {
        return $this->collected()->take($limit)->values();
    }

    /**
     * Everyone together this calendar month: portions picked up, and
     * portions offered to food banks (declined offers left out).
     *
     * @return array{meals: int, donated: int}
     */
    public static function everyoneThisMonth(): array
    {
        $monthStart = now()->startOfMonth();

        return [
            'meals' => (int) Reservation::where('status', ReservationStatus::Collected)
                ->where('collected_at', '>=', $monthStart)
                ->sum('quantity'),
            'donated' => (int) Donation::where('status', '!=', DonationStatus::Declined)
                ->where('created_at', '>=', $monthStart)
                ->sum('quantity'),
        ];
    }
}
