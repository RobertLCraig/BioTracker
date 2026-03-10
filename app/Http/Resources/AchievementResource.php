<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'icon'            => $this->icon,
            'condition_type'  => $this->condition_type,
            'condition_value' => $this->condition_value,
            'points_reward'   => $this->points_reward,
            'tier'            => $this->tier?->value,
            'unlocked_at'     => $this->pivot?->unlocked_at ?? null,
            'is_unlocked'     => $this->pivot !== null,
        ];
    }
}
