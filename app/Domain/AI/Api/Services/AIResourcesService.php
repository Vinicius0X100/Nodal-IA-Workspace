<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Database\Eloquent\Collection;

class AIResourcesService
{
    /**
     * Search resources for the given organization.
     */
    public function search(Organization $organization, string $query): Collection
    {
        $integrationIds = $organization->integrations()->pluck('id');

        return IntegrationResource::whereIn('integration_id', $integrationIds)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('mime_type', 'like', "%{$query}%")
                  ->orWhere('provider', 'like', "%{$query}%");
            })
            ->orderBy('updated_by_provider_at', 'desc')
            ->limit(50) // Limit for safety
            ->get();
    }

    /**
     * Find a specific resource by UUID within the organization's scope.
     */
    public function findByUuid(Organization $organization, string $uuid): ?IntegrationResource
    {
        $integrationIds = $organization->integrations()->pluck('id');

        return IntegrationResource::whereIn('integration_id', $integrationIds)
            ->where('uuid', $uuid)
            ->first();
    }
}
