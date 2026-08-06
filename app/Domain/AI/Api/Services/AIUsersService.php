<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

class AIUsersService
{
    /**
     * Get all users for the given organization.
     */
    public function getOrganizationUsers(Organization $organization): Collection
    {
        return $organization->users()->orderBy('name', 'asc')->get();
    }
}
