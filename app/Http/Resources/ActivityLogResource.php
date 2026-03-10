<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'activity_type'    => new ActivityTypeResource($this->whenLoaded('activityType')),
            'activity_type_id' => $this->activity_type_id,
            'logged_at'        => $this->logged_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'quantity'         => $this->quantity,
            'unit'             => $this->unit,
            'calories'         => $this->calories,
            'notes'            => $this->notes,
            'metadata'         => $this->metadata,
            'photos'           => $this->getMedia('photos')->map(fn ($media) => [
                'id'      => $media->id,
                'url'     => $media->getUrl(),
                'thumb'   => $media->getUrl('thumb'),
                'preview' => $media->getUrl('preview'),
            ]),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
