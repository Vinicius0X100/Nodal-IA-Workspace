<?php

namespace App\Domain\Permissions\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use App\Domain\Permissions\Contexts\AuthorizedAccessContext;
use App\Domain\Identities\Services\IdentityContextService;

class AuthorizationService
{
    public function __construct(
        protected IdentityContextService $identityContextService
    ) {}
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
     * Obtains the allowed scopes for a user and capability.
     */
    public function getAuthorizedScopes(User $user, Organization $organization, string $capability): array
    {
        $membership = $user->organizations()->wherePivot('organization_id', $organization->id)->first();
        if ($membership && $membership->pivot->is_owner) {
            return ['self', 'group', 'organization'];
        }

        $roles = $user->roles()->wherePivot('organization_id', $organization->id)->with('permissions')->get();
        
        $scopes = [];
        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->slug === $capability) {
                    $scope = $permission->pivot->scope ?? 'organization'; // Fallback for safety or legacy
                    if (!in_array($scope, $scopes, true)) {
                        $scopes[] = $scope;
                    }
                }
            }
        }

        return $scopes;
    }

    /**
     * Resolves the access context for a specific capability, including scopes and external identities.
     * Optionally resolves a target user if provided.
     *
     * @throws \App\Domain\Identities\Exceptions\ExternalIdentityRequiredException
     * @throws \App\Domain\Identities\Exceptions\TargetIdentityNotFoundException
     * @throws AuthorizationException
     */
    public function resolveAccessContext(
        User $user,
        Organization $organization,
        string $capability,
        ?\App\Domain\Integrations\Models\Integration $integration = null,
        string $provider = 'google_workspace',
        ?User $targetUser = null
    ): AuthorizedAccessContext {
        $this->authorize($user, $organization, $capability);

        $allowedScopes = $this->getAuthorizedScopes($user, $organization, $capability);

        $activeIdentity = null;
        $targetIdentity = null;

        if ($integration) {
            try {
                $activeIdentity = $this->identityContextService->getRequiredExternalIdentity($user, $integration, $provider);
            } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
                throw $e;
            }

            if ($targetUser) {
                // TODO: valid scopes (group/organization) before allowing target
                if (!in_array('organization', $allowedScopes, true)) {
                    // For now, if no organization scope, block target query unless it's the user itself
                    if ($targetUser->id !== $user->id) {
                        throw new AuthorizationException("Seu nível de acesso não permite consultar dados de outros usuários.");
                    }
                }
                
                $targetIdentity = $this->identityContextService->getTargetExternalIdentity($targetUser, $integration, $provider);
            }
        }

        return new AuthorizedAccessContext(
            $organization,
            $user,
            $capability,
            $allowedScopes,
            $activeIdentity,
            $targetUser,
            $targetIdentity
        );
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
