<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DonationStatus;
use App\Http\Controllers\Admin\Concerns\AttemptsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Queries\AdminOverview;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The partner food bank and the leftovers offered to it. The admin keeps the
 * partner's details up to date and records what happened to each offer.
 */
class FoodBankController extends Controller
{
    use AttemptsAdminActions;

    public function __construct(private AdminService $admin) {}

    public function index(AdminOverview $overview): View
    {
        $donations = $overview->donations();

        // Portions per outcome, added up in one query.
        $portions = Donation::query()->toBase()
            ->selectRaw('status, sum(quantity) as portions')
            ->groupBy('status')
            ->pluck('portions', 'status');

        return view('admin.food-banks', [
            'partners' => FoodBankPartner::query()->orderBy('id')->get(),
            'donations' => $donations,
            'portions' => [
                'waiting' => (int) $portions->get(DonationStatus::Offered->value, 0),
                'collected' => (int) $portions->get(DonationStatus::Collected->value, 0),
                'declined' => (int) $portions->get(DonationStatus::Declined->value, 0),
            ],
        ]);
    }

    public function update(Request $request, FoodBankPartner $partner): RedirectResponse
    {
        $details = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'The food bank needs a name.',
        ]);

        $this->admin->updatePartner($partner, $details);

        return back()->with('status', 'Food bank details saved.');
    }

    public function settle(Request $request, Donation $donation): RedirectResponse
    {
        $outcome = DonationStatus::from($request->validate([
            'outcome' => ['required', Rule::in([DonationStatus::Collected->value, DonationStatus::Declined->value])],
        ], [
            'outcome.*' => 'Choose collected or declined.',
        ])['outcome']);

        return $this->attempt(
            fn () => $this->admin->settleDonation($donation, $outcome),
            fn (Donation $donation) => $outcome === DonationStatus::Collected
                ? "Marked as collected: {$donation->quantity} × {$donation->listing->title}. The shop has been told."
                : "Marked as declined: {$donation->quantity} × {$donation->listing->title}. The shop has been told.",
        );
    }
}
