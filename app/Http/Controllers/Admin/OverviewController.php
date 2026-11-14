<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminOverview;
use App\Services\AdminService;
use Illuminate\View\View;

/** The admin's home page (mockup c10): things that need a decision come first. */
class OverviewController extends Controller
{
    public function __invoke(AdminOverview $overview, AdminService $admin): View
    {
        return view('admin.overview', [
            'stats' => $overview->stats(),
            'sellers' => $overview->sellersToApprove(),
            'reported' => $overview->reportedListings(),
            'customers' => $overview->customersWithStrikes(),
            'totals' => $overview->totals(),
            'admin' => $admin,
        ]);
    }
}
