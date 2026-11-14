<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Who may do what with a report about a listing.
 */
class ReportPolicy
{
    /** Customers report listings. */
    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /** The person who sent it, and admins. */
    public function view(User $user, Report $report): bool
    {
        return $report->reporter_id === $user->id || $user->isAdmin();
    }

    /** Action or dismiss a report: admins only. */
    public function resolve(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}
