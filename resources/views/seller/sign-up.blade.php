{{-- "Sell on FoodLoop": sign-up for shops. App\Http\Controllers\Seller\SignUpController. --}}
@php use App\Enums\HalalStatus; @endphp

<x-layouts.auth title="Sell on FoodLoop">
    <x-slot:intro>Sell what’s left at the end of the day to students nearby, instead of throwing it away. An admin checks every shop before its listings go live.</x-slot:intro>

    <form method="POST" action="{{ route('seller.sign-up.store') }}" enctype="multipart/form-data" class="space-y-4"
          x-data="{ claim: @js(old('halal_claim', '')) }">
        @csrf

        <h2 class="pt-2 font-display text-xl font-bold">You</h2>
        <x-form.input name="name" label="Your name" autocomplete="name" required autofocus />
        <x-form.input name="email" label="Email" type="email" autocomplete="username" inputmode="email" required />
        <x-form.input name="password" label="Password" type="password" autocomplete="new-password" required hint="At least 8 characters." />
        <x-form.input name="password_confirmation" label="Password again" type="password" autocomplete="new-password" required />

        <h2 class="pt-4 font-display text-xl font-bold">Your shop</h2>
        <x-form.input name="business_name" label="Shop name" autocomplete="organization" required maxlength="80" />
        <x-form.input name="ssm_no" label="SSM registration number" required maxlength="50" />
        <x-form.input name="phone" label="Phone" type="tel" autocomplete="tel" required maxlength="30" />
        <x-form.input name="address" label="Address" autocomplete="street-address" required maxlength="255" />

        <fieldset>
            <legend class="text-sm font-semibold">Halal status</legend>
            <div class="mt-1 space-y-1">
                @foreach (HalalStatus::cases() as $option)
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="radio" name="halal_claim" value="{{ $option->value }}" x-model="claim"
                               @checked(old('halal_claim') === $option->value) required class="size-4 accent-forest">
                        {{ $option->label() }}{{ $option === HalalStatus::Certified ? ' (JAKIM)' : '' }}
                    </label>
                @endforeach
            </div>
            @error('halal_claim') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
        </fieldset>

        <div x-show="claim === 'certified'">
            <label for="halal_certificate" class="block text-sm font-semibold">JAKIM certificate</label>
            <input id="halal_certificate" name="halal_certificate" type="file" accept="application/pdf,image/jpeg,image/png"
                   @error('halal_certificate') aria-invalid="true" aria-describedby="cert-error" @else aria-describedby="cert-hint" @enderror
                   class="mt-1.5 block w-full text-sm file:mr-3 file:min-h-11 file:rounded-xl file:border-2 file:border-forest file:bg-white file:px-4 file:font-semibold">
            @error('halal_certificate')
                <p id="cert-error" class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p>
            @else
                <p id="cert-hint" class="mt-1.5 text-sm text-muted">PDF, JPG or PNG, up to 5 MB. Only FoodLoop admins can see it.</p>
            @enderror
        </div>

        <x-button type="submit" size="lg" arrow>Send for approval</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        Already selling on FoodLoop?
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-forest underline underline-offset-4">Log in</a>
    </p>
</x-layouts.auth>
