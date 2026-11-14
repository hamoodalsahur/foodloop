<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AttemptsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\AdminOverview;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Customers with no-show strikes or a limit, and lifting a limit early. */
class CustomerController extends Controller
{
    use AttemptsAdminActions;

    public function index(AdminOverview $overview): View
    {
        return view('admin.customers', ['customers' => $overview->customersWithStrikes()]);
    }

    public function liftLimit(User $user, AdminService $admin): RedirectResponse
    {
        return $this->attempt(
            fn () => $admin->liftLimit($user),
            "{$user->name} can reserve again and starts from zero strikes.",
        );
    }
}
