<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'dosage'        => 'nullable|string|max:100',
            'unit'          => 'nullable|string|max:50',
            'frequency'     => 'nullable|string|max:100',
            'prescribed_by' => 'nullable|string|max:255',
            'notes'         => 'nullable|string|max:2000',
            'is_active'     => 'boolean',
        ];
    }
}
