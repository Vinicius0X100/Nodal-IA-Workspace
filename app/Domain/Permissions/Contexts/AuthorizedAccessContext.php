<?php

namespace App\Domain\Permissions\Contexts;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;

class AuthorizedAccessContext
{
    public function __construct(
        public Organization $organization,
        public User $activeUser,
        public string $capability,
        public array $allowedScopes,
        public ?ExternalIdentity $externalIdentity = null,
        public ?User $targetUser = null,
        public ?ExternalIdentity $targetExternalIdentity = null
    ) {
    }

    /**
     * Checks if a specific scope was granted to this access context.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->allowedScopes, true);
    }

    /**
     * Helper to get the identity that should be used for provider API calls.
     * For scope=self, this is the active user's identity.
     * For target user queries, this is the target user's identity.
     * Returns null if no specific identity was resolved (e.g., organization-wide operation).
     */
    public function getResolvedIdentity(): ?ExternalIdentity
    {
        return $this->targetExternalIdentity ?? $this->externalIdentity;
    }
}
