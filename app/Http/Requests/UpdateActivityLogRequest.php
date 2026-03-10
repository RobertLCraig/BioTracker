<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_type_id' => 'sometimes|integer|exists:activity_types,id',
            'logged_at'        => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'quantity'         => 'nullable|numeric|min:0',
            'unit'             => 'nullable|string|max:50',
            'calories'         => 'nullable|integer|min:0',
            'notes'            => 'nullable|string|max:2000',
            'metadata'         => 'nullable|array',
        ];
    }
}
