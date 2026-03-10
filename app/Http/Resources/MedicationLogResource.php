<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'medication'    => new MedicationResource($this->whenLoaded('medication')),
            'medication_id' => $this->medication_id,
            'taken_at'      => $this->taken_at->toIso8601String(),
            'dosage_taken'  => $this->dosage_taken,
            'notes'         => $this->notes,
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}
