<?php

namespace App\Domain\AI\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIToolResource extends JsonResource
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
            'provider' => $this->provider,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'endpoint' => $this->endpoint,
            'http_method' => $this->http_method,
            'tool_type' => $this->tool_type,
            'requires_confirmation' => $this->requires_confirmation,
            'configuration' => $this->configuration_json,
        ];
    }
}
