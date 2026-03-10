<?php

namespace App\Http\Requests;

use App\Enums\BloodAmount;
use App\Enums\ExcretionSize;
use App\Enums\ExcretionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExcretionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::enum(ExcretionType::class)],
            'size'        => ['nullable', Rule::enum(ExcretionSize::class)],
            'consistency' => [
                'nullable',
                'integer',
                'min:1',
                'max:7',
                Rule::requiredIf(fn () => $this->input('type') === ExcretionType::Poop->value),
            ],
            'colour'       => 'nullable|string|max:100',
            'has_blood'    => 'boolean',
            'blood_amount' => ['nullable', Rule::enum(BloodAmount::class)],
            'urgency'      => 'nullable|integer|min:1|max:5',
            'pain_level'   => 'nullable|integer|min:0|max:10',
            'logged_at'    => 'required|date',
            'notes'        => 'nullable|string|max:2000',
            'photos'       => 'nullable|array|max:10',
            'photos.*'     => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ];
    }
}
