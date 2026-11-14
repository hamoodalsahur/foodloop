<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Admin\Concerns\AttemptsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Queries\AdminOverview;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Approving, rejecting and suspending shops, and looking at their halal certificate. */
class SellerController extends Controller
{
    use AttemptsAdminActions;

    public function __construct(private AdminService $admin) {}

    public function index(AdminOverview $overview): View
    {
        $sellers = $overview->sellers();

        return view('admin.sellers', [
            'groups' => [
                'Waiting for approval' => $sellers->where('status', SellerStatus::Pending)->sortBy('created_at'),
                'Approved' => $sellers->where('status', SellerStatus::Approved),
                'Suspended' => $sellers->where('status', SellerStatus::Suspended),
                'Rejected' => $sellers->where('status', SellerStatus::Rejected),
            ],
            'admin' => $this->admin,
        ]);
    }

    public function approve(Seller $seller): RedirectResponse
    {
        Gate::authorize('review', $seller);

        return $this->attempt(
            fn () => $this->admin->approveSeller($seller),
            "{$seller->business_name} is approved and can put listings live.",
        );
    }

    public function reject(Seller $seller): RedirectResponse
    {
        Gate::authorize('review', $seller);

        return $this->attempt(fn () => $this->admin->rejectSeller($seller), "{$seller->business_name} was rejected.");
    }

    public function suspend(Seller $seller): RedirectResponse
    {
        Gate::authorize('review', $seller);

        return $this->attempt(
            fn () => $this->admin->suspendSeller($seller),
            "{$seller->business_name} is suspended. Its listings are off the deals page.",
        );
    }

    /** Show the uploaded halal certificate. It is kept in private storage, so only admins get it. */
    public function certificate(Seller $seller): StreamedResponse
    {
        Gate::authorize('review', $seller);

        abort_if(blank($seller->halal_cert_path) || ! Storage::disk('local')->exists($seller->halal_cert_path), 404);

        return Storage::disk('local')->response($seller->halal_cert_path);
    }
}
