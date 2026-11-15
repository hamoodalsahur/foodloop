<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every listing that is on FoodLoop right now, and the ones that were taken
 * down. "Hide listing" posts to Admin\ReportController@hide, the same action
 * as on the Reports page.
 */
class ListingController extends Controller
{
    public function __invoke(Request $request, AdminOverview $overview): View
    {
        $show = $request->query('show') === 'hidden' ? 'hidden' : 'now';

        return view('admin.listings', [
            'show' => $show,
            'listings' => $overview->listings($show),
        ]);
    }
}
