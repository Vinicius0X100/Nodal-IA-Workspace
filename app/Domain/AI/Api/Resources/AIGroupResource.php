<?php

namespace App\Domain\AI\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'description' => $this->description,
            'provider' => $this->provider,
            'members_count' => $this->users_count ?? $this->users()->count(),
        ];
    }
}
