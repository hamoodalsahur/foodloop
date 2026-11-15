<?php

namespace App\Http\Controllers\Seller;

use App\Enums\HalalStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The seller's shop details. Status and the halal claim are not editable
 * here: they are what the admin approved (changing them goes through an admin).
 *
 * One exception: while the shop is still waiting for approval and says it is
 * halal certified, it can upload (or replace) its certificate here. That is
 * how it answers an admin's "Ask for info" about a missing certificate.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);
        Gate::authorize('update', $shop);

        return view('seller.profile', ['shop' => $shop, 'canUploadCertificate' => $this->canUploadCertificate($shop)]);
    }

    public function update(Request $request, Notifier $notifier): RedirectResponse
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);
        Gate::authorize('update', $shop);

        $details = $request->validate([
            'business_name' => ['required', 'string', 'max:80'],
            'ssm_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'halal_certificate' => $this->canUploadCertificate($shop)
                ? ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']
                : ['prohibited'],
        ], [
            'business_name.required' => 'Your shop needs a name.',
            'halal_certificate.mimes' => 'The certificate has to be a PDF, JPG or PNG.',
            'halal_certificate.max' => 'The certificate can be up to 5 MB.',
            'halal_certificate.prohibited' => 'Your certificate can only be changed by an admin now.',
        ]);

        // Certificates are kept in private storage: only admins can open them.
        if ($certificate = $request->file('halal_certificate')) {
            $old = $shop->halal_cert_path;
            $details['halal_cert_path'] = $certificate->store('halal-certs', 'local');

            if (filled($old)) {
                Storage::disk('local')->delete($old);
            }
        }

        unset($details['halal_certificate']);
        $shop->update($details);

        // Saving the profile is how a shop answers the admin's "Ask for info".
        if ($shop->info_request !== null) {
            $shop->forceFill(['info_request' => null, 'info_requested_at' => null])->save();
            $notifier->shopAnswered($shop);

            return redirect()->route('seller.profile.edit')
                ->with('status', 'Shop details saved. The admin has been told you’ve updated them.');
        }

        return redirect()->route('seller.profile.edit')->with('status', 'Shop details saved.');
    }

    private function canUploadCertificate(Seller $shop): bool
    {
        return $shop->status === SellerStatus::Pending && $shop->halal_claim === HalalStatus::Certified;
    }
}
