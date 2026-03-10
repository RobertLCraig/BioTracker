<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'dosage'        => $this->dosage,
            'unit'          => $this->unit,
            'frequency'     => $this->frequency,
            'prescribed_by' => $this->prescribed_by,
            'notes'         => $this->notes,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}
