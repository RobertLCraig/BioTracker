<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSymptomLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symptom'          => 'required|string|max:255',
            'severity'         => 'required|integer|min:1|max:10',
            'body_area'        => 'nullable|string|max:100',
            'logged_at'        => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string|max:2000',
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ];
    }
}
