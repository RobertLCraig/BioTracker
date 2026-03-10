<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'totp_enabled'         => $this->totp_enabled,
            'privacy_consented_at' => $this->privacy_consented_at?->toIso8601String(),
            'terms_accepted_at'    => $this->terms_accepted_at?->toIso8601String(),
            'created_at'           => $this->created_at->toIso8601String(),
        ];
    }
}
