<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SymptomLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'symptom'          => $this->symptom,
            'severity'         => $this->severity,
            'body_area'        => $this->body_area,
            'logged_at'        => $this->logged_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'notes'            => $this->notes,
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
