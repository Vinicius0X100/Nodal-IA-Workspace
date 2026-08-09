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

    /**
     * Get the groups a specific user belongs to within the organization.
     * Returns an array with the user and their groups, or null if the user is not found in the organization.
     */
    public function getUserGroups(Organization $organization, \App\Domain\Identity\Models\User $activeUser, string $targetUserUuid): ?array
    {
        $targetUser = $organization->users()->where('users.uuid', $targetUserUuid)->first();

        if (!$targetUser) {
            return null;
        }

        // Fetch groups belonging to the organization that the user is a member of
        $groups = $targetUser->groups()
            ->where('groups.organization_id', $organization->id)
            ->get();

        return [
            'user' => $targetUser,
            'groups' => $groups,
        ];
    }
}
