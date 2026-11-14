<?php

namespace App\Http\Controllers;

use App\Queries\CustomerImpact;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A customer's impact page (mockup c04): what they rescued and saved. Customers only. */
class ImpactController extends Controller
{
    public function __invoke(Request $request): View
    {
        $impact = new CustomerImpact($request->user());

        return view('impact.index', [
            'meals' => $impact->mealsRescued(),
            'savedSen' => $impact->savedSen(),
            'weeks' => $impact->weeks(),
            'recent' => $impact->recent(),
            'everyone' => CustomerImpact::everyoneThisMonth(),
            'strikes' => $request->user()->no_show_strikes,
        ]);
    }
}
