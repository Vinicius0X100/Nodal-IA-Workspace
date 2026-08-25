<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Providers\GoogleWorkspace\GoogleWorkspaceConnector;
use InvalidArgumentException;

class IntegrationManager
{
    /**
     * Retorna a instância do conector com base no nome do provider
     */
    public function getConnector(string $provider): ConnectorInterface
    {
        return match ($provider) {
            'google_workspace' => app(GoogleWorkspaceConnector::class),
            'meta' => app(\App\Domain\Integrations\Providers\Meta\MetaConnector::class),
            // 'microsoft_365' => app(Microsoft365Connector::class),
            // 'slack' => app(SlackConnector::class),
            default => throw new InvalidArgumentException("Conector não suportado: {$provider}"),
        };
    }
}
