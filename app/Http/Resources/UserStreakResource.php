<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStreakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'current_streak'   => $this->current_streak,
            'longest_streak'   => $this->longest_streak,
            'last_logged_date' => $this->last_logged_date?->toDateString(),
            'is_active'        => $this->isActive(),
        ];
    }
}
