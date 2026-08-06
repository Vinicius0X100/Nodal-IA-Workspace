<?php

namespace App\Domain\Resources\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Enums\Provider;
use Exception;

class ResourceSyncService
{
    public function __construct(
        private GoogleDriveSyncService $googleDriveSyncService
    ) {
    }

    /**
     * Sincroniza os recursos baseado no provedor da integração.
     */
    public function sync(Integration $integration): void
    {
        $provider = Provider::tryFrom($integration->provider->value ?? $integration->provider);

        if (!$provider) {
            throw new Exception("Provider {$integration->provider} não suportado para sincronização de resources.");
        }

        match ($provider) {
            Provider::GOOGLE_WORKSPACE => $this->googleDriveSyncService->sync($integration),
            // Outros provedores serão adicionados aqui futuramente (ex: Microsoft 365)
        };
    }
}
