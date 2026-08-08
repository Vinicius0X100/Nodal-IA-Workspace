<?php

namespace App\Domain\Permissions\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;

class AuthorizationService
{
    /**
     * Verifica se o usuário tem a permissão dentro do contexto da organização.
     * Owner da organização recebe bypass automático.
     */
    public function can(User $user, Organization $organization, string $capability): bool
    {
        // 1. Verifica bypass de Owner
        $membership = $user->organizations()->wherePivot('organization_id', $organization->id)->first();
        if ($membership && $membership->pivot->is_owner) {
            return true;
        }

        // 2. Busca todas as roles do usuário na organização
        $roles = $user->roles()->wherePivot('organization_id', $organization->id)->with('permissions')->get();

        // 3. Verifica se alguma role possui a permissão requisitada
        foreach ($roles as $role) {
            if ($role->permissions->contains('slug', $capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lança exceção caso o usuário não tenha permissão.
     *
     * @throws AuthorizationException
     */
    public function authorize(User $user, Organization $organization, string $capability): void
    {
        if (!$this->can($user, $organization, $capability)) {
            // Pode registrar na auditoria tentativas negadas de forma centralizada aqui
            \App\Domain\Audit\Models\AuditLog::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'action' => 'authorization.denied',
                'entity_type' => get_class($user),
                'entity_id' => $user->id,
                'metadata' => [
                    'capability' => $capability,
                    'description' => "Tentativa de acesso negado para a capacidade: {$capability}",
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            throw new AuthorizationException("Você não possui a permissão necessária ({$capability}).");
        }
    }

    /**
     * Segurança em nível de recurso (granular).
     * Preparado para expansões futuras (ex: verificar se resource pertence a um grupo de TI).
     */
    public function canAccessResource(User $user, Organization $organization, \App\Domain\Resources\Models\IntegrationResource $resource): bool
    {
        // Por padrão, se chegou até aqui, já exigimos que ele tenha `resources.read` via middleware/controller.
        // Se no futuro houverem tags ou domínios nos recursos (ex: HR, Financeiro), a verificação ficará aqui.
        
        // Exemplo: se o resource exigir 'finance.read' e o usuário não tiver, retorna false.
        
        return true; 
    }
}
