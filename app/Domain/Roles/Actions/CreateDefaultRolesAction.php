<?php

namespace App\Domain\Roles\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Models\Role;
use App\Domain\Permissions\Models\Permission;
use Illuminate\Support\Str;

class CreateDefaultRolesAction
{
    public function execute(Organization $organization, User $owner): void
    {
        // Owner Role
        $ownerRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'description' => 'Acesso total e irrestrito.',
            'is_system' => true,
        ]);
        
        // Admin Role
        Role::create([
            'organization_id' => $organization->id,
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => 'Acesso a configurações e integrações.',
            'is_system' => true,
        ]);

        // Member Role
        Role::create([
            'organization_id' => $organization->id,
            'name' => 'Membro',
            'slug' => 'member',
            'description' => 'Acesso padrão.',
            'is_system' => true,
        ]);

        // Associa todas as permissões existentes ao owner
        $allPermissions = Permission::pluck('id')->toArray();
        if (!empty($allPermissions)) {
            $ownerRole->permissions()->sync($allPermissions);
        }

        // Atribui owner role ao usuário criador
        $owner->roles()->attach($ownerRole->id, ['organization_id' => $organization->id]);
    }
}
