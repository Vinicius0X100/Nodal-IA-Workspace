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

    /**
     * Get all groups with their members and count.
     */
    public function getGroupsWithMembers(Organization $organization, \App\Domain\Identity\Models\User $activeUser, bool $onlyWithMembers): array
    {
        $query = $organization->groups()
            ->withCount('users')
            ->with(['users' => function($query) {
                $query->select('users.id', 'users.uuid', 'users.name', 'users.email');
            }])
            ->orderBy('name', 'asc');

        if ($onlyWithMembers) {
            $query->has('users');
        }

        $groups = $query->get();

        $result = $groups->map(function ($group) {
            $members = $group->users->map(function ($user) {
                return [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            })->toArray();

            return [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'email' => $group->email,
                'members_count' => $group->users_count,
                'members' => $members,
            ];
        })->toArray();

        // Registrar auditoria da IA consultando dados
        \App\Domain\Audit\Models\AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $activeUser->id,
            'action' => 'ai_read_all_groups_with_members',
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
            'metadata' => [
                'total_groups_fetched' => count($result),
                'only_with_members_filter' => $onlyWithMembers,
                'requested_by_ai' => true,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $result;
    }

    /**
     * Get members of a specific group in an organization.
     */
    public function getGroupMembers(Organization $organization, \App\Domain\Identity\Models\User $activeUser, string $groupUuid): array
    {
        // Isola o tenant: Garante que o grupo pertence à organização atual.
        $group = $organization->groups()
            ->where('uuid', $groupUuid)
            ->with(['users' => function($query) {
                // Eager loading e selecionando apenas campos seguros
                $query->select('users.id', 'users.uuid', 'users.name', 'users.email');
            }])
            ->firstOrFail();

        $members = $group->users->map(function ($user) {
            return [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ];
        })->toArray();

        // Registrar auditoria da IA consultando dados
        \App\Domain\Audit\Models\AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $activeUser->id,
            'action' => 'ai_read_group_members',
            'entity_type' => get_class($group),
            'entity_id' => $group->id, // Usa o ID interno no banco de dados para entity_id
            'metadata' => [
                'group_uuid' => $group->uuid,
                'total_members_fetched' => count($members),
                'requested_by_ai' => true,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'group' => [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'email' => $group->email, // Grupo pode não ter e-mail, mas retornamos conforme solicitado
            ],
            'members' => $members,
            'total' => count($members),
        ];
    }
}
