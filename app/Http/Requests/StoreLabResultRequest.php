<?php

namespace App\Http\Requests;

use App\Enums\AbnormalFlag;
use App\Enums\LabResultStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_name'        => 'required|string|max:255',
            'value_text'       => 'required|string|max:255',
            'value_numeric'    => 'nullable|numeric',
            'value_comparator' => 'nullable|string|in:<,>',
            'unit'             => 'nullable|string|max:50',
            'range_low'        => 'nullable|numeric',
            'range_high'       => 'nullable|numeric',
            'range_text'       => 'nullable|string|max:255',
            'abnormal_flag'    => ['nullable', Rule::enum(AbnormalFlag::class)],
            'status'           => ['nullable', Rule::enum(LabResultStatus::class)],
            'textual_only'     => 'nullable|boolean',
            'sampled_at'       => 'nullable|date',
            'released_at'      => 'nullable|date',
            'comment'          => 'nullable|string|max:2000',
            'test_code'        => 'nullable|string|max:100',
            'lab_order_id'     => 'nullable|string|max:100',
        ];
    }
}
