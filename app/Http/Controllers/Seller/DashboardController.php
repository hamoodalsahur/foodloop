<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Queries\SellerOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The seller's home page (mockup c08): tonight's numbers, pickups, live listings, leftovers. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $shop = $request->user()->seller;

        if ($shop === null) {
            return view('seller.no-shop');
        }

        $overview = new SellerOverview($shop);

        return view('seller.dashboard', [
            'shop' => $shop,
            'stats' => $overview->stats(),
            'pickups' => $overview->pickups(),
            'listings' => $overview->activeListings(),
            'leftovers' => $overview->leftoversToOffer(),
        ]);
    }
}
