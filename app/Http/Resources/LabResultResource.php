<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_name' => $this->test_name,
            'test_key' => $this->test_key,
            'test_code' => $this->test_code,
            'value' => [
                'text' => $this->value_text,
                'numeric' => $this->value_numeric,
                'comparator' => $this->value_comparator,
                'unit' => $this->unit,
            ],
            'range' => [
                'low' => $this->range_low,
                'high' => $this->range_high,
                'text' => $this->range_text,
            ],
            'abnormal_flag' => $this->abnormal_flag?->value,
            'status' => $this->status?->value,
            'textual_only' => $this->textual_only,
            'sampled_at' => $this->sampled_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'available_from' => $this->available_from?->toIso8601String(),
            'comment' => $this->comment,
            'lab_panel_id' => $this->lab_panel_id,
            'definition' => $this->whenLoaded('testDefinition', fn () => [
                'id' => $this->testDefinition->id,
                'name' => $this->testDefinition->name,
                'category' => $this->testDefinition->category,
            ]),
            'panel' => new LabPanelResource($this->whenLoaded('labPanel')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
