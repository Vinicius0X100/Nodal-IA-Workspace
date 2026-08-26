<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Abstração leve para resolver uma Integration pertencente a uma Organization.
 *
 * Evita que cada Service Meta (e futuros) replique manualmente a mesma query
 * de resolução de tenant (organization_id + provider).
 *
 * Não depende de session() — recebe Organization explicitamente para uso
 * seguro em Web, Queue, AI Gateway e Scheduler.
 */
class IntegrationResolver
{
    /**
     * Resolve a Integration conectada de um provider para uma organização.
     * Lança ModelNotFoundException se não existir ou não estiver conectada.
     *
     * @throws ModelNotFoundException
     */
    public function resolveOrFail(Organization $organization, string $provider): Integration
    {
        return Integration::where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->where('status', 'connected')
            ->firstOrFail();
    }

    /**
     * Resolve a Integration sem exigir status 'connected'.
     * Útil para contextos de health check ou reconexão.
     *
     * @throws ModelNotFoundException
     */
    public function resolveAnyOrFail(Organization $organization, string $provider): Integration
    {
        return Integration::where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->firstOrFail();
    }

    /**
     * Resolve a Integration ou retorna null se não existir.
     */
    public function resolve(Organization $organization, string $provider): ?Integration
    {
        return Integration::where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();
    }
}
