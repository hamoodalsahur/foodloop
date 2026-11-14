<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreReportRequest;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;

/** A customer reports a listing to the admins (the form is on the listing page). */
class ReportController extends Controller
{
    public function store(StoreReportRequest $request, Listing $listing): RedirectResponse
    {
        // Only listings the public can see can be reported.
        abort_unless($listing->isPublished(), 404);

        $alreadyOpen = $listing->reports()
            ->where('reporter_id', $request->user()->id)
            ->where('status', ReportStatus::Open)
            ->exists();

        if ($alreadyOpen) {
            return redirect()->route('listings.show', $listing)
                ->withErrors(['report' => 'You’ve already reported this listing. An admin is looking at it.']);
        }

        $report = new Report($request->safe()->only(['reason', 'details']));
        $report->reporter()->associate($request->user());
        $report->listing()->associate($listing);
        $report->save();

        return redirect()->route('listings.show', $listing)
            ->with('status', 'Thanks. An admin will look at your report.');
    }
}
