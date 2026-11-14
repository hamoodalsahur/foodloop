<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\HalalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Sell on FoodLoop": a separate sign-up for shops (owner's decision,
 * 14 Nov). Creates a seller account and a shop profile waiting for an
 * admin's approval. The halal certificate is kept private (local disk).
 */
class SignUpController extends Controller
{
    use PasswordValidationRules;

    public function create(): View
    {
        return view('seller.sign-up');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'business_name' => ['required', 'string', 'max:80'],
            'ssm_no' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'halal_claim' => ['required', Rule::enum(HalalStatus::class)],
            'halal_certificate' => [
                Rule::requiredIf($request->input('halal_claim') === HalalStatus::Certified->value),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],
        ], [
            'business_name.required' => 'Enter your shop’s name.',
            'ssm_no.required' => 'Enter your SSM registration number.',
            'halal_claim.required' => 'Choose your halal status.',
            'halal_certificate.required' => 'Upload your JAKIM certificate to be listed as halal certified.',
            'halal_certificate.mimes' => 'The certificate has to be a PDF, JPG or PNG.',
            'halal_certificate.max' => 'The certificate can be up to 5 MB.',
        ]);

        $certPath = $request->file('halal_certificate')?->store('halal-certs', 'local');

        $user = DB::transaction(function () use ($input, $certPath) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
            // role is not fillable on purpose: only trusted code like this sets it.
            $user->forceFill(['role' => UserRole::Seller])->save();

            $shop = new Seller([
                'business_name' => $input['business_name'],
                'ssm_no' => $input['ssm_no'],
                'phone' => $input['phone'],
                'address' => $input['address'],
                'halal_claim' => $input['halal_claim'],
                'halal_cert_path' => $input['halal_claim'] === HalalStatus::Certified->value ? $certPath : null,
            ]);
            $shop->user()->associate($user);
            $shop->save();

            return $user;
        });

        if ($certPath !== null && $input['halal_claim'] !== HalalStatus::Certified->value) {
            Storage::disk('local')->delete($certPath);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('seller.dashboard')
            ->with('status', 'Your shop is set up and waiting for approval. You can write drafts meanwhile.');
    }
}
