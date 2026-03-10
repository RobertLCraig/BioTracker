<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'icon'           => $this->icon,
            'points_per_log' => $this->points_per_log,
            'is_system'      => $this->is_system,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
