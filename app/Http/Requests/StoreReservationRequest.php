<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "Reserve" form on a listing page.
 *
 * This only checks that the form was filled in sensibly and that the person
 * is a customer. Whether the reservation is possible right now (stock,
 * pickup window, blocks…) is decided by App\Services\ReservationService.
 */
class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Reservation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('foodloop.max_qty_per_listing')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Choose how many you want.',
            'quantity.integer' => 'Choose a whole number.',
            'quantity.min' => 'Choose at least 1.',
            'quantity.max' => 'You can reserve up to :max of each listing.',
            'payment_method.required' => 'Choose how you want to pay.',
            'payment_method.enum' => 'Choose how you want to pay.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }
}
