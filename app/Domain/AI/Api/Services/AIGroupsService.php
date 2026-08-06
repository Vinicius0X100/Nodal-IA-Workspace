<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

class AIGroupsService
{
    /**
     * Get all groups for the given organization.
     */
    public function getOrganizationGroups(Organization $organization): Collection
    {
        return $organization->groups()->withCount('users')->orderBy('name', 'asc')->get();
    }
}
