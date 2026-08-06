<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;

class AIOrganizationService
{
    /**
     * Get the active organization with relationships loaded for AI Resource.
     */
    public function getOrganizationDetails(Organization $organization): Organization
    {
        return $organization->loadCount(['users', 'groups'])->load('integrations');
    }
}
