<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The "Report this listing" form on a listing page. Customers only. */
class StoreReportRequest extends FormRequest
{
    /** Validation errors go into their own bag, so they show inside the report form. */
    protected $errorBag = 'report';

    public function authorize(): bool
    {
        return $this->user()->can('create', Report::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:500', Rule::requiredIf($this->input('reason') === ReportReason::Other->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Choose what is wrong.',
            'reason.enum' => 'Choose what is wrong.',
            'details.required' => 'Tell us what is wrong, in a few words.',
            'details.max' => 'Keep it under :max characters.',
        ];
    }
}
