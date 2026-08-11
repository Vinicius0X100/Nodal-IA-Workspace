<?php

namespace App\Domain\Identities\Services;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Identities\Exceptions\ExternalIdentityRequiredException;
use App\Domain\Identities\Exceptions\TargetIdentityNotFoundException;

class IdentityContextService
{
    /**
     * Obtains the primary external identity for an active user on a specific integration.
     * Throws an exception if the user does not have a linked identity.
     *
     * @throws ExternalIdentityRequiredException
     */
    public function getRequiredExternalIdentity(User $user, Integration $integration, string $provider): ExternalIdentity
    {
        $identity = $user->externalIdentities()
            ->where('integration_id', $integration->id)
            ->where('provider', $provider)
            ->where('status', 'linked')
            ->first();

        if (!$identity) {
            throw new ExternalIdentityRequiredException(
                "Sua conta Nodal precisa ser vinculada à sua conta corporativa ({$provider}) para acessar este recurso."
            );
        }

        return $identity;
    }

    /**
     * Obtains the external identity for a target user.
     * Throws if the target user doesn't have an identity in the specified integration.
     *
     * @throws TargetIdentityNotFoundException
     */
    public function getTargetExternalIdentity(User $targetUser, Integration $integration, string $provider): ExternalIdentity
    {
        $identity = $targetUser->externalIdentities()
            ->where('integration_id', $integration->id)
            ->where('provider', $provider)
            ->where('status', 'linked')
            ->first();

        if (!$identity) {
            throw new TargetIdentityNotFoundException(
                "O usuário solicitado não possui uma conta corporativa vinculada ({$provider})."
            );
        }

        return $identity;
    }
}
