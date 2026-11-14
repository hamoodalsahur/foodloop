<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Queries\SellerOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Leftovers waiting to be offered, and what has gone to the food bank. */
class DonationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);

        $overview = new SellerOverview($shop);

        return view('seller.donations', [
            'leftovers' => $overview->leftoversToOffer(),
            'donations' => $overview->donations(),
            'donated' => $overview->donatedPortions(),
        ]);
    }
}
