<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

/**
 * What happened to one order, in order, for the admin's order page.
 *
 * Built only from what the reservation itself recorded: when it was made,
 * collected or cancelled, and when its pickup time ran out. Nothing is
 * guessed. A cancel does not record who cancelled (the customer, or
 * FoodLoop after a limit or a takedown), so the timeline doesn't say.
 */
class OrderTimeline
{
    /**
     * The steps, oldest first. `at` is null for the one step that hasn't
     * happened yet ("Waiting for pickup").
     *
     * @return list<array{at: Carbon|null, title: string, note: string|null}>
     */
    public static function for(Reservation $reservation): array
    {
        $listing = $reservation->listing;
        $payNow = $reservation->payment_method === PaymentMethod::PayNow;

        $steps = [[
            'at' => $reservation->created_at,
            'title' => 'Reserved',
            'note' => $reservation->quantity.' × '.$listing->title.' for '.Money::format($reservation->total_sen)
                .', pickup code '.$reservation->pickup_code.'.',
        ]];

        if ($payNow) {
            $steps[] = ['at' => $reservation->created_at, 'title' => 'Paid (simulated)', 'note' => 'Paid when reserving. No real money moves.'];
        }

        switch ($reservation->status) {
            case ReservationStatus::Reserved:
                $steps[] = [
                    'at' => null,
                    'title' => 'Waiting for pickup',
                    'note' => 'Pickup '.PickupWindow::withDay($listing->pickup_start, $listing->pickup_end)
                        .'. Held until '.self::moment($reservation->expires_at).', then it becomes a no-show.',
                ];
                break;

            case ReservationStatus::Collected:
                $admin = $reservation->collectedByAdmin;

                $steps[] = [
                    'at' => $reservation->collected_at,
                    'title' => $reservation->isAdminOverride()
                        ? 'Collected by '.($admin?->name ?? 'an admin').' (admin override)'
                        : 'Collected at the shop',
                    'note' => $reservation->isAdminOverride()
                        ? 'Marked collected from Admin → Orders, not with the pickup code at the shop.'
                        : 'The shop checked pickup code '.$reservation->pickup_code.'.',
                ];

                if (! $payNow) {
                    $steps[] = ['at' => $reservation->collected_at, 'title' => 'Paid at pickup (simulated)', 'note' => 'Counted as paid when it was collected.'];
                }
                break;

            case ReservationStatus::Cancelled:
                $steps[] = [
                    'at' => $reservation->cancelled_at,
                    'title' => $reservation->late_cancel ? 'Cancelled late' : 'Cancelled',
                    'note' => $reservation->late_cancel
                        ? 'After the free-cancel time, so it counted as a strike. The food went back on sale.'
                        : 'No strike. The food went back on sale.',
                ];

                if ($reservation->payment_status === PaymentStatus::Refunded) {
                    $steps[] = ['at' => $reservation->cancelled_at, 'title' => 'Refunded (simulated)', 'note' => 'The pay-now payment was given back.'];
                }
                break;

            case ReservationStatus::NoShow:
                $steps[] = [
                    'at' => $reservation->expires_at,
                    'title' => 'No-show',
                    'note' => 'Not collected by the end of the pickup time. One strike for the customer; the portions count as leftovers.'
                        .($payNow ? ' Not refunded.' : ''),
                ];
                break;
        }

        return $steps;
    }

    /** "Mon 16 Nov, 9:15 PM" */
    public static function moment(?Carbon $at): string
    {
        return $at?->format('D j M, g:i A') ?? '';
    }
}
