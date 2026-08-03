<?php

namespace App\Domain\Roles\Actions;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Models\Role;
use Illuminate\Support\Str;

class CreateRoleAction
{
    public function execute(Organization $organization, array $data): Role
    {
        return Role::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);
    }
}
