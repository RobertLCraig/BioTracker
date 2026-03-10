<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medication_id' => 'required|integer|exists:medications,id',
            'taken_at'      => 'required|date',
            'dosage_taken'  => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:2000',
        ];
    }
}
