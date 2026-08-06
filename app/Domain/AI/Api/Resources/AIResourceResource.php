<?php

namespace App\Domain\AI\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIResourceResource extends JsonResource
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
            'type' => $this->resource_type,
            'provider' => $this->provider,
            'url' => $this->url,
            'owner' => [
                'name' => $this->owner_name,
                'email' => $this->owner_email,
            ],
            'last_modified' => $this->updated_by_provider_at,
            'metadata' => $this->metadata_json,
        ];
    }
}
