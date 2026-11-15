<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Turn on deal alerts" (mockup c05): a notification in the customer's
 * inbox for every new deal. In-app only; the prototype sends no push or SMS.
 */
class DealAlertController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $on = $request->validate(['on' => ['required', 'boolean']])['on'];

        $request->user()->forceFill(['deal_alerts' => (bool) $on])->save();

        return back()->with('status', $on
            ? 'Deal alerts are on. New deals will show up under the bell.'
            : 'Deal alerts are off.');
    }
}
