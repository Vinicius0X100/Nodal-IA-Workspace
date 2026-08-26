<?php

namespace App\Domain\AI\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource para sanitização de IntegrationResource Meta.
 *
 * Garante que campos internos (external_id, parent_external_id,
 * integration_id, organization_id, access_token) NUNCA escapem
 * para a camada AI/n8n.
 */
class AIMetaResourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource_type instanceof \BackedEnum
            ? $this->resource_type->value
            : (string) $this->resource_type;

        $metadata = $this->metadata_json ?? [];

        $data = [
            'uuid' => $this->uuid,
            'type' => $type,
            'name' => $this->name,
            'status' => $metadata['effective_status'] ?? $metadata['status'] ?? null,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];

        // Campos condicionais por tipo de resource
        match ($type) {
            'ad_account' => $data = array_merge($data, [
                'currency' => $metadata['currency'] ?? null,
                'timezone' => $metadata['timezone_name'] ?? null,
            ]),
            'campaign' => $data = array_merge($data, [
                'objective' => $metadata['objective'] ?? null,
                'daily_budget' => $metadata['daily_budget'] ?? null,
                'lifetime_budget' => $metadata['lifetime_budget'] ?? null,
            ]),
            'ad_set' => $data = array_merge($data, [
                'optimization_goal' => $metadata['optimization_goal'] ?? null,
                'billing_event' => $metadata['billing_event'] ?? null,
                'daily_budget' => $metadata['daily_budget'] ?? null,
            ]),
            'ad' => $data = array_merge($data, [
                'creative_id' => null, // Oculta creative ID externo
            ]),
            'facebook_page' => $data = array_merge($data, [
                'category' => $metadata['category'] ?? null,
            ]),
            'instagram_account' => $data = array_merge($data, [
                'username' => $metadata['username'] ?? null,
            ]),
            default => null,
        };

        // Resolve parent para UUID interno se existir
        if ($this->parent_external_id) {
            $parentUuid = \App\Domain\Resources\Models\IntegrationResource::where('integration_id', $this->integration_id)
                ->where('external_id', $this->parent_external_id)
                ->value('uuid');
            if ($parentUuid) {
                $data['parent_uuid'] = $parentUuid;
            }
        }

        // Children opcionais (carregados externamente)
        if ($this->relationLoaded('children_resources')) {
            $data['children'] = static::collection($this->children_resources);
        }

        return $data;
    }

    /**
     * Cria representação compacta para listagens dentro de outro resource (ex: ad_account dentro de campaign).
     */
    public static function compact($resource): array
    {
        return [
            'uuid' => $resource->uuid,
            'name' => $resource->name,
        ];
    }
}
