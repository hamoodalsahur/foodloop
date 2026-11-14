<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AttemptsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Queries\AdminOverview;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reports from customers, grouped by listing. Each button deals with every
 * open report on that listing at once.
 */
class ReportController extends Controller
{
    use AttemptsAdminActions;

    public function __construct(private AdminService $admin) {}

    public function index(AdminOverview $overview): View
    {
        return view('admin.reports', [
            'reported' => $overview->reportedListings(),
            'resolved' => $overview->resolvedReports(),
        ]);
    }

    public function hide(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('moderate', $listing);

        return $this->attempt(
            fn () => $this->admin->hideListing($listing, $request->user()),
            fn (int $cancelled) => "“{$listing->title}” is taken down."
                .($cancelled > 0 ? " {$cancelled} waiting ".Str::plural('reservation', $cancelled).' '.($cancelled === 1 ? 'was' : 'were').' cancelled and refunded.' : ''),
        );
    }

    public function warn(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('moderate', $listing);

        return $this->attempt(
            fn () => $this->admin->warnSeller($listing, $request->user()),
            "{$listing->seller->business_name} will see a warning about “{$listing->title}” on their dashboard.",
        );
    }

    public function dismiss(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('moderate', $listing);

        return $this->attempt(
            fn () => $this->admin->dismissReports($listing, $request->user()),
            fn (int $count) => $count === 1 ? 'Report dismissed.' : "{$count} reports dismissed.",
        );
    }
}
