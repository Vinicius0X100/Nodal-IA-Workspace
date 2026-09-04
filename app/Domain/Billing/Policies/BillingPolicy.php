<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;

/**
 * Policy de Billing.
 *
 * Utiliza o sistema de permissões existente (slug-based).
 * Nunca cria um segundo ACL.
 */
class BillingPolicy
{
    /**
     * Verifica se o usuário possui uma capability dentro da organização atual.
     */
    private function hasCapability(User $user, Organization $organization, string $slug): bool
    {
        $isOwner = $organization->users()->where('users.id', $user->id)->wherePivot('is_owner', true)->exists();
        if ($isOwner) {
            return true;
        }

        return $user->roles()
            ->where('user_roles.organization_id', $organization->id)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->contains('slug', $slug);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'billing.view')
            || $this->hasCapability($user, $organization, 'billing.manage');
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'billing.manage');
    }

    public function manageAlerts(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'billing.alerts.manage')
            || $this->hasCapability($user, $organization, 'billing.manage');
    }

    public function viewInvoices(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'billing.invoices.view')
            || $this->hasCapability($user, $organization, 'billing.view')
            || $this->hasCapability($user, $organization, 'billing.manage');
    }

    public function viewAiUsageOrganization(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'ai_usage.view_organization')
            || $this->hasCapability($user, $organization, 'billing.view')
            || $this->hasCapability($user, $organization, 'billing.manage');
    }

    public function viewAiUsageByUser(User $user, Organization $organization): bool
    {
        return $this->hasCapability($user, $organization, 'ai_usage.view_users')
            || $this->hasCapability($user, $organization, 'billing.manage');
    }
}
