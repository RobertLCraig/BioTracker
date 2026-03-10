<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'points_reward' => $this->points_reward,
            'is_recurring'  => $this->is_recurring,
            'frequency'     => $this->frequency?->value,
            'is_active'     => $this->is_active,
            'completions'   => $this->when(
                $this->relationLoaded('completions'),
                fn () => $this->completions->map(fn ($c) => [
                    'id'           => $c->id,
                    'completed_at' => $c->completed_at->toIso8601String(),
                ])
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
