<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VitalLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type?->value,
            'value'           => $this->value,
            'secondary_value' => $this->secondary_value,
            'unit'            => $this->unit,
            'logged_at'       => $this->logged_at->toIso8601String(),
            'source'          => $this->source,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
        ];
    }
}
