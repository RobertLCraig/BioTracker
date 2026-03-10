<?php

namespace App\Http\Requests;

use App\Enums\VitalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVitalLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'            => ['required', Rule::enum(VitalType::class)],
            'value'           => 'required|numeric',
            'secondary_value' => 'nullable|string|max:50',
            'unit'            => 'required|string|max:50',
            'logged_at'       => 'required|date',
            'source'          => 'nullable|string|in:manual,fitbit,apple_health,health_connect',
            'notes'           => 'nullable|string|max:2000',
        ];
    }
}
