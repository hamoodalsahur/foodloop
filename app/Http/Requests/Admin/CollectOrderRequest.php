<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The "Yes, mark collected" button of the admin override.
 *
 * Only checks who is asking and that they came through the confirm step.
 * Whether the order can still be collected is decided by
 * App\Services\ReservationService::collectAsAdmin().
 */
class CollectOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('collectAsAdmin', $this->route('reservation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Nothing changed. Press “Mark collected (admin override)”, then confirm.',
        ];
    }
}
