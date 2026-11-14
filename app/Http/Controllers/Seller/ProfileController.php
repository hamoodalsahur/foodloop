<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The seller's shop details. Status and the halal claim are not editable
 * here: they are what the admin approved (changing them goes through an admin).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);
        Gate::authorize('update', $shop);

        return view('seller.profile', ['shop' => $shop]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);
        Gate::authorize('update', $shop);

        $shop->update($request->validate([
            'business_name' => ['required', 'string', 'max:80'],
            'ssm_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'business_name.required' => 'Your shop needs a name.',
        ]));

        return redirect()->route('seller.profile.edit')->with('status', 'Shop details saved.');
    }
}
