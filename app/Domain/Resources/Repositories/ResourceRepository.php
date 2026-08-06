<?php

namespace App\Domain\Resources\Repositories;

use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResourceRepository
{
    /**
     * Upsert resources into the database.
     * Updates based on integration_id and external_id.
     */
    public function upsertResources(array $resources): void
    {
        IntegrationResource::upsert(
            $resources,
            ['integration_id', 'external_id'],
            [
                'parent_external_id',
                'name',
                'description',
                'mime_type',
                'url',
                'icon',
                'owner_name',
                'owner_email',
                'is_folder',
                'is_shared',
                'size',
                'created_by_provider_at',
                'updated_by_provider_at',
                'last_synced_at',
                'metadata_json',
            ]
        );
    }

    /**
     * Get a base query for resources belonging to a specific organization.
     */
    public function queryForOrganization(string $organizationId): Builder
    {
        return IntegrationResource::whereHas('integration', function (Builder $query) use ($organizationId) {
            $query->where('organization_id', $organizationId);
        });
    }

    /**
     * Find a resource by its UUID and Organization ID to ensure security.
     */
    public function findByUuid(string $organizationId, string $uuid): ?IntegrationResource
    {
        return $this->queryForOrganization($organizationId)
            ->where('uuid', $uuid)
            ->first();
    }
}
