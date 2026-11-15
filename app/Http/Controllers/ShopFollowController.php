<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Notify me next time" (mockup c07): a customer asks to be told when one
 * shop posts again. The message itself is sent by App\Services\Notifier
 * when a listing of that shop goes live.
 */
class ShopFollowController extends Controller
{
    public function store(Request $request, Seller $seller): RedirectResponse
    {
        // Only shops the public can see.
        abort_unless($seller->isApproved(), 404);

        $request->user()->followedShops()->syncWithoutDetaching([$seller->id]);

        return back()->with('status', "Done. You’ll get a notification the next time {$seller->business_name} posts.");
    }

    public function destroy(Request $request, Seller $seller): RedirectResponse
    {
        $request->user()->followedShops()->detach($seller->id);

        return back()->with('status', "You won’t be notified about {$seller->business_name} any more.");
    }
}
