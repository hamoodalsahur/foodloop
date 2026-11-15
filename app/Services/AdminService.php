<?php

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\SellerStatus;
use App\Exceptions\AdminActionRefused;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything an admin decides: approving shops (or asking them for more
 * info first), acting on reports, taking listings down, lifting a customer's
 * no-show limit, and keeping the food bank records.
 *
 * Who may do it is checked in the controllers (admin-only routes plus the
 * policies). This class holds the rules about WHEN it may happen.
 */
class AdminService
{
    public function __construct(private ReservationService $reservations, private Notifier $notifier) {}

    /**
     * Why this shop can't be approved yet, or null when it can.
     * Used for the page (to hide the Approve button) and by approveSeller().
     */
    public function approvalBlocker(Seller $seller): ?string
    {
        return match (true) {
            blank($seller->ssm_no) => 'This shop has no SSM number yet, so it can’t be approved.',
            $seller->halal_claim === HalalStatus::Certified && blank($seller->halal_cert_path) => 'This shop says it is halal certified but hasn’t uploaded a certificate, so it can’t be approved.',
            default => null,
        };
    }

    /**
     * Approve a shop (first time, or again after a rejection or suspension).
     * From then on its listings can go live.
     *
     * @throws AdminActionRefused
     */
    public function approveSeller(Seller $seller): Seller
    {
        $seller = DB::transaction(function () use ($seller) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->getKey());

            if ($seller->status === SellerStatus::Approved) {
                throw new AdminActionRefused('This shop is already approved.');
            }

            if ($blocker = $this->approvalBlocker($seller)) {
                throw new AdminActionRefused($blocker);
            }

            $seller->forceFill([
                'status' => SellerStatus::Approved,
                'approved_at' => now(),
                'info_request' => null,
                'info_requested_at' => null,
            ])->save();

            return $seller;
        });

        $this->notifier->shopApproved($seller);

        return $seller;
    }

    /**
     * "Ask for info": send a shop that is waiting for approval a question.
     * The shop sees it on its pages until it saves its profile again (then
     * the admins are told) or a decision is made. Asking again replaces the
     * earlier question.
     *
     * @throws AdminActionRefused
     */
    public function askForInfo(Seller $seller, string $question): Seller
    {
        $seller = DB::transaction(function () use ($seller, $question) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->getKey());

            if ($seller->status !== SellerStatus::Pending) {
                throw new AdminActionRefused('Only a shop that is waiting for approval can be asked for more info.');
            }

            $seller->forceFill(['info_request' => trim($question), 'info_requested_at' => now()])->save();

            return $seller;
        });

        $this->notifier->infoRequested($seller);

        return $seller;
    }

    /**
     * Turn down a shop that is waiting for approval.
     *
     * @throws AdminActionRefused
     */
    public function rejectSeller(Seller $seller): Seller
    {
        $seller = DB::transaction(function () use ($seller) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->getKey());

            if ($seller->status !== SellerStatus::Pending) {
                throw new AdminActionRefused('Only a shop that is waiting for approval can be rejected. To stop an approved shop, suspend it.');
            }

            $seller->forceFill([
                'status' => SellerStatus::Rejected,
                'approved_at' => null,
                'info_request' => null,
                'info_requested_at' => null,
            ])->save();

            return $seller;
        });

        $this->notifier->shopRejected($seller);

        return $seller;
    }

    /**
     * Stop an approved shop from selling. Its listings leave the deals page
     * straight away (they need an approved shop); reservations already made
     * can still be collected.
     *
     * @throws AdminActionRefused
     */
    public function suspendSeller(Seller $seller): Seller
    {
        $seller = DB::transaction(function () use ($seller) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->getKey());

            if ($seller->status !== SellerStatus::Approved) {
                throw new AdminActionRefused('Only an approved shop can be suspended.');
            }

            $seller->forceFill(['status' => SellerStatus::Suspended])->save();

            return $seller;
        });

        $this->notifier->shopSuspended($seller);

        return $seller;
    }

    /**
     * Take a listing off FoodLoop (after a report, or straight from the admin
     * Listings page). It can't be reserved or
     * seen by the public any more and the seller can't put it back. Waiting
     * reservations are cancelled for the customers (refunded, no strike).
     * Every open report on the listing is closed as "listing taken down".
     *
     * Returns how many reservations were cancelled.
     *
     * @throws AdminActionRefused
     */
    public function hideListing(Listing $listing, User $admin): int
    {
        $summary = DB::transaction(function () use ($listing, $admin) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if ($listing->status === ListingStatus::Hidden) {
                throw new AdminActionRefused('This listing has already been taken down.');
            }

            if ($listing->status === ListingStatus::Draft) {
                throw new AdminActionRefused('This listing was never published, so there is nothing to take down.');
            }

            $listing->status = ListingStatus::Hidden;
            $listing->closed_at ??= now();
            $listing->save();

            return $this->resolveOpenReports($listing, $admin, ReportStatus::Actioned, ReportResolution::ListingHidden)[1];
        });

        // After the listing is hidden, so nobody can reserve the freed portions.
        $cancelled = $this->reservations->cancelWaitingReservationsOn($listing);

        $this->notifier->listingTakenDown($listing, $summary, $cancelled);

        return $cancelled;
    }

    /**
     * Leave the listing up but tell the seller what customers reported. The
     * seller sees the warning on their dashboard. Closes the open reports as
     * "seller warned".
     *
     * @throws AdminActionRefused
     */
    public function warnSeller(Listing $listing, User $admin): int
    {
        [$resolved, $summary] = DB::transaction(function () use ($listing, $admin) {
            $result = $this->resolveOpenReports($listing, $admin, ReportStatus::Actioned, ReportResolution::SellerWarned);

            if ($result[0] === 0) {
                throw new AdminActionRefused('There is no open report on this listing to warn the seller about.');
            }

            return $result;
        });

        $this->notifier->sellerWarned($listing, $summary);

        return $resolved;
    }

    /**
     * Close the open reports on a listing without doing anything.
     *
     * @throws AdminActionRefused
     */
    public function dismissReports(Listing $listing, User $admin): int
    {
        return DB::transaction(function () use ($listing, $admin) {
            [$resolved] = $this->resolveOpenReports($listing, $admin, ReportStatus::Dismissed, null);

            if ($resolved === 0) {
                throw new AdminActionRefused('There is no open report on this listing.');
            }

            return $resolved;
        });
    }

    /**
     * End a customer's no-show limit early and wipe their strikes.
     *
     * @throws AdminActionRefused
     */
    public function liftLimit(User $customer): User
    {
        $customer = DB::transaction(function () use ($customer) {
            $customer = User::query()->lockForUpdate()->findOrFail($customer->getKey());

            if (! $customer->isCustomer()) {
                throw new AdminActionRefused('Only customer accounts have no-show limits.');
            }

            if (! $customer->isBlocked() && $customer->no_show_strikes === 0) {
                throw new AdminActionRefused('This customer has no strikes and no limit.');
            }

            $customer->forceFill(['no_show_strikes' => 0, 'blocked_until' => null])->save();

            return $customer;
        });

        $this->notifier->limitLifted($customer);

        return $customer;
    }

    /**
     * Change the food bank partner's name or contact.
     *
     * @param  array{name: string, contact: string|null}  $details
     */
    public function updatePartner(FoodBankPartner $partner, array $details): FoodBankPartner
    {
        $partner->update($details);

        return $partner;
    }

    /**
     * Record what happened to leftovers that were offered: the food bank
     * collected them, or couldn't take them. Decided once.
     *
     * @throws AdminActionRefused
     */
    public function settleDonation(Donation $donation, DonationStatus $outcome): Donation
    {
        $donation = DB::transaction(function () use ($donation, $outcome) {
            $donation = Donation::query()->lockForUpdate()->findOrFail($donation->getKey());

            if ($outcome === DonationStatus::Offered) {
                throw new AdminActionRefused('Choose collected or declined.');
            }

            if ($donation->status !== DonationStatus::Offered) {
                throw new AdminActionRefused('This offer was already marked '.str($donation->status->label())->lower().'.');
            }

            $donation->status = $outcome;
            $donation->save();

            return $donation;
        });

        $this->notifier->donationSettled($donation);

        return $donation;
    }

    /**
     * Close every open report on a listing the same way. The note that is
     * saved (and, for a warning, shown to the seller) is built from what the
     * customers wrote, without saying who they are.
     *
     * @return array{0: int, 1: string} how many reports were closed, and that note
     */
    private function resolveOpenReports(Listing $listing, User $admin, ReportStatus $status, ?ReportResolution $resolution): array
    {
        $reports = $listing->reports()->where('status', ReportStatus::Open)->lockForUpdate()->get();

        $summary = $reports
            ->map(fn (Report $report) => $report->reason->label().(filled($report->details) ? ': “'.$report->details.'”' : ''))
            ->unique()
            ->implode(' · ');

        $reports->each(fn (Report $report) => $report->forceFill([
            'status' => $status,
            'resolution' => $resolution,
            'admin_note' => $resolution ? $summary : null,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ])->save());

        return [$reports->count(), $summary];
    }
}
