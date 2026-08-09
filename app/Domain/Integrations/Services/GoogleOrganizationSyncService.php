<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationOrganization;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Providers\GoogleWorkspace\GoogleWorkspaceConnector;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleOrganizationSyncService
{
    public function __construct(
        protected GoogleWorkspaceConnector $connector
    ) {}

    public function sync(Integration $integration): IntegrationOrganization
    {
        try {
            $this->logEvent($integration, 'organization_sync_started', 'in_progress', 'Iniciando sincronização da organização do Google Workspace.');

            // Busca os dados através do conector
            $data = $this->connector->getOrganizationData($integration);

            // Atualiza ou cria a organização
            $organizationData = IntegrationOrganization::updateOrCreate(
                ['integration_id' => $integration->id],
                [
                    'customer_id' => $data['customer_id'],
                    'organization_name' => $data['organization_name'],
                    'primary_domain' => $data['primary_domain'],
                    'customer_type' => $data['customer_type'],
                    'admin_email' => $data['admin_email'],
                    'admin_name' => $data['admin_name'],
                    'total_users' => $data['total_users'],
                    'total_groups' => $data['total_groups'],
                    'organization_json' => $data['original_response'],
                    'last_synced_at' => now(),
                ]
            );

            // Atualiza o last_sync_at da integração principal
            $integration->update(['last_sync_at' => now()]);

            // Sincroniza também todos os grupos que já foram importados (traz os membros atualizados)
            $directorySyncService = app(\App\Domain\Integrations\Services\GoogleDirectorySyncService::class);
            $directorySyncService->syncImportedGroups($integration);

            $this->logEvent($integration, 'organization_sync_completed', 'success', 'Organização sincronizada com sucesso. Usuários: ' . $data['total_users']);

            return $organizationData;

        } catch (Exception $e) {
            $this->logEvent($integration, 'organization_sync_failed', 'error', 'Falha na sincronização: ' . $e->getMessage());
            Log::error("Erro no GoogleOrganizationSyncService", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    protected function logEvent(Integration $integration, string $event, string $status, string $message): void
    {
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'user_id' => auth()->id(), // Pode ser nulo se for via cron, precisa tratar de acordo
            'event' => $event,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
