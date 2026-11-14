<?php

namespace App\Http\Requests\Seller;

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Services\SellerListingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The seller's create / edit listing form (mockup c09).
 *
 * Prices are typed in ringgit ("8.00") and stored in sen (800). Pickup is a
 * date plus a start and end time, in Malaysian time. Once someone has
 * reserved, the price, quantity, pickup time and halal status are locked and
 * not read from the form at all (see SellerListingService::LOCKED_FIELDS).
 */
class SaveListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $this->user()->can('update', $listing)
            : $this->user()->can('create', Listing::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locked = $this->isLocked();
        $lockable = fn (array $rules) => $locked ? ['exclude'] : $rules;

        return [
            'action' => ['required', Rule::in(['draft', 'publish'])],
            'type' => ['required', Rule::enum(ListingType::class)],
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::enum(ListingCategory::class)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => [Rule::in(config('foodloop.allergens'))],
            'offer_leftovers' => ['nullable', 'boolean'],

            'original_price' => $lockable(['required', 'numeric', 'decimal:0,2', 'min:1', 'max:500']),
            'price' => $lockable(['required', 'numeric', 'decimal:0,2', 'min:0.5', 'lt:original_price']),
            'quantity' => $lockable(['required', 'integer', 'min:1', 'max:'.config('foodloop.max_portions_per_listing')]),
            'pickup_date' => $lockable(['required', 'date_format:Y-m-d', 'after_or_equal:today']),
            'pickup_start' => $lockable(['required', 'date_format:H:i']),
            'pickup_end' => $lockable(['required', 'date_format:H:i', 'after:pickup_start']),
            'halal_status' => $lockable(['required', Rule::enum(HalalStatus::class)]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the listing a name.',
            'category.required' => 'Choose a category.',
            'photo.image' => 'The photo has to be a JPG, PNG or WebP picture.',
            'photo.mimes' => 'The photo has to be a JPG, PNG or WebP picture.',
            'photo.max' => 'The photo can be up to 5 MB.',
            'original_price.required' => 'Enter the normal price.',
            'original_price.min' => 'The normal price is at least RM1.',
            'price.required' => 'Enter the FoodLoop price.',
            'price.lt' => 'The FoodLoop price has to be lower than the normal price.',
            'price.min' => 'The FoodLoop price is at least RM0.50.',
            'quantity.required' => 'Enter how many portions you have.',
            'quantity.min' => 'Offer at least 1 portion.',
            'quantity.max' => 'One listing can offer up to :max portions.',
            'pickup_date.after_or_equal' => 'Pickup can’t be in the past.',
            'pickup_end.after' => 'Pickup has to end after it starts.',
            'halal_status.required' => 'Choose the halal status.',
            '*.decimal' => 'Use a price like 8 or 8.50.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->isLocked() || $validator->errors()->hasAny(['pickup_date', 'pickup_start', 'pickup_end'])) {
                return;
            }

            [$start, $end] = $this->pickupTimes();

            if ($start->diffInMinutes($end) > config('foodloop.max_window_hours') * 60) {
                $validator->errors()->add('pickup_end', 'A pickup window can be up to '.config('foodloop.max_window_hours').' hours long.');
            }
            if ($this->input('action') === 'publish' && ! $start->isFuture()) {
                $validator->errors()->add('pickup_start', 'Reservations close when pickup starts, so pickup has to start later than now.');
            }

            if ($this->input('halal_status') === HalalStatus::Certified->value
                && ! $this->user()->seller?->hasApprovedHalalCertificate()) {
                $validator->errors()->add('halal_status', '“Halal certified” needs an approved JAKIM certificate on your shop profile.');
            }
        }];
    }

    public function wantsToPublish(): bool
    {
        return $this->validated('action') === 'publish';
    }

    /**
     * The validated values, ready for the model (sen, Carbon times).
     *
     * @return array<string, mixed>
     */
    public function listingData(): array
    {
        $data = [
            'type' => $this->validated('type'),
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'category' => $this->validated('category'),
            'allergens' => array_values($this->validated('allergens') ?? []),
            'offer_leftovers' => $this->boolean('offer_leftovers'),
        ];

        if (! $this->isLocked()) {
            [$start, $end] = $this->pickupTimes();
            $data += [
                'original_price_sen' => (int) round((float) $this->validated('original_price') * 100),
                'price_sen' => (int) round((float) $this->validated('price') * 100),
                'quantity_total' => (int) $this->validated('quantity'),
                'pickup_start' => $start,
                'pickup_end' => $end,
                'halal_status' => $this->validated('halal_status'),
            ];
        }

        return $data;
    }

    /** @return array{Carbon, Carbon} */
    private function pickupTimes(): array
    {
        $tz = config('app.timezone');
        $date = $this->input('pickup_date');

        return [
            Carbon::createFromFormat('Y-m-d H:i', $date.' '.$this->input('pickup_start'), $tz)->startOfMinute(),
            Carbon::createFromFormat('Y-m-d H:i', $date.' '.$this->input('pickup_end'), $tz)->startOfMinute(),
        ];
    }

    private function isLocked(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && app(SellerListingService::class)->isLocked($listing);
    }
}
