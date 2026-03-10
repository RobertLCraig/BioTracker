<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'points'      => $this->points,
            'reason'      => $this->reason,
            'source_type' => class_basename($this->source_type),
            'source_id'   => $this->source_id,
            'earned_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
