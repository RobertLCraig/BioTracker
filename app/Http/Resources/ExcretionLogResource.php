<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcretionLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type?->value,
            'size'         => $this->size?->value,
            'consistency'  => $this->consistency?->value,
            'bristol_label'=> $this->consistency?->label(),
            'colour'       => $this->colour,
            'has_blood'    => $this->has_blood,
            'blood_amount' => $this->blood_amount?->value,
            'urgency'      => $this->urgency,
            'pain_level'   => $this->pain_level,
            'logged_at'    => $this->logged_at->toIso8601String(),
            'notes'        => $this->notes,
            'photos'       => $this->getMedia('photos')->map(fn ($media) => [
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
