<?php

namespace App\Domain\Roles\Actions;

use App\Domain\Roles\Models\Role;

class SyncRolePermissionsAction
{
    public function execute(Role $role, array $permissionIds): void
    {
        // Se for um papel do sistema (ex: Owner), idealmente bloqueamos edição total, 
        // ou deixamos a UI bloquear. Aqui assumimos que a UI já cuidou disso.
        
        $role->permissions()->sync($permissionIds);
    }
}
