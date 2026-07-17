<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabPanelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lab_order_id' => $this->lab_order_id,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'reported_at' => $this->reported_at?->toIso8601String(),
            'source' => $this->source,
            'performing_org' => $this->performing_org,
            'lab_type' => $this->lab_type,
            'notes' => $this->notes,
            'results' => LabResultResource::collection($this->whenLoaded('results')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
