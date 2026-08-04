<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;

class RemoveUserFromOrganizationAction
{
    public function execute(User $user, Organization $organization): void
    {
        DB::transaction(function () use ($user, $organization) {
            
            // 1. Remove os vínculos de Role (Grupos) nesta organização
            $user->roles()->wherePivot('organization_id', $organization->id)->detach();
            
            // 2. Remove o vínculo com a Organização
            $organization->users()->detach($user->id);

            // Opcional: Se o usuário não pertencer a mais NENHUMA organização, podemos excluí-lo
            // para evitar contas órfãs, mas por padrão de segurança B2B mantemos a conta.
            if ($user->organizations()->count() === 0) {
                // Se quiser apagar os dados totalmente:
                // $user->delete();
            }
        });
    }
}
