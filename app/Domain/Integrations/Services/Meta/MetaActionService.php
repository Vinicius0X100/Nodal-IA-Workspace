<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\AI\Models\AIAction;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaActionService
{
    public function __construct(
        private MetaMarketingClient $client
    ) {}

    /**
     * Executa fisicamente na Meta Graph API a ação pendente de alteração de status.
     * Atualiza o estado da AIAction para 'executing' dentro de uma transação.
     * O request HTTP externo ocorre FORA da transação.
     */
    public function executeStatusUpdate(string $actionUuid): array
    {
        // 1. Transição isolada de estado (Pessimistic Lock)
        $action = DB::transaction(function () use ($actionUuid) {
            $action = AIAction::where('uuid', $actionUuid)->lockForUpdate()->first();

            if (!$action) {
                throw new \InvalidArgumentException('Ação não encontrada.');
            }

            if ($action->isExecuting()) {
                throw new \InvalidArgumentException('Esta ação já está sendo executada no momento.');
            }

            if (!$action->isPending()) {
                throw new \InvalidArgumentException('Esta ação já foi resolvida (executada ou falhou).');
            }

            if ($action->isExpired()) {
                throw new \InvalidArgumentException('A validade desta ação já expirou.');
            }

            $integration = $action->integration;
            
            $resource = IntegrationResource::where('uuid', $action->target_resource_uuid)
                ->where('integration_id', $integration->id)
                ->first();

            if (!$resource) {
                $action->update([
                    'status' => 'failed',
                    'error_data' => ['message' => 'O recurso alvo não foi encontrado ou não pertence a esta integração.']
                ]);
                throw new \InvalidArgumentException('Recurso alvo não encontrado.');
            }

            // Snapshot Conflict Validation
            $currentStatus = $resource->metadata_json['effective_status'] ?? null;
            $snapshotStatus = $action->snapshot['effective_status'] ?? null;

            if ($currentStatus !== $snapshotStatus) {
                $action->update([
                    'status' => 'failed',
                    'error_data' => ['message' => 'O status do recurso foi alterado por outra fonte desde a preparação da ação (conflito de estado).']
                ]);
                return ['error' => 'Conflito de estado do recurso.', 'type' => 'invalid_argument'];
            }

            $targetStatus = $action->prepared_params['status'] ?? null;
            if (!$targetStatus) {
                $action->update(['status' => 'failed', 'error_data' => ['message' => 'Status alvo não definido na ação.']]);
                return ['error' => 'Status alvo não definido na ação.', 'type' => 'invalid_argument'];
            }

            if ($currentStatus === $targetStatus) {
                $action->update([
                    'status' => 'failed',
                    'error_data' => ['message' => 'O recurso já se encontra no estado desejado. Nenhuma ação será realizada.']
                ]);
                return ['error' => 'O recurso já se encontra no estado desejado.', 'type' => 'invalid_argument'];
            }

            // Transição segura para EXECUTING
            $action->update(['status' => 'executing']);
            
            Log::info('[MetaActionService] Ação entrou em executing', [
                'action_uuid' => $action->uuid,
                'organization_id' => $action->organization_id,
                'user_id' => $action->user_id,
                'target_resource_uuid' => $action->target_resource_uuid,
            ]);

            return $action;
        });

        if (is_array($action) && isset($action['error'])) {
            if ($action['type'] === 'invalid_argument') {
                throw new \InvalidArgumentException($action['error']);
            }
        }

        // 2. Execução HTTP (FORA da transação de banco)
        $integration = $action->integration;
        $resource = IntegrationResource::where('uuid', $action->target_resource_uuid)->first();
        
        $targetStatus = $action->prepared_params['status'];
        $externalId = $resource->external_id;
        $endpoint = "/{$externalId}";

        try {
            $response = $this->client->post($endpoint, $integration, [
                'status' => $targetStatus
            ]);

            // Atualiza o Action (Sucesso)
            $action->update([
                'status' => 'executed',
                'executed_at' => now(),
                'result_data' => $response,
            ]);

            // Atualiza o estado local do resource para refletir a mudança imediatamente
            $metadata = $resource->metadata_json ?? [];
            $metadata['effective_status'] = $targetStatus;
            
            $resource->update([
                'metadata_json' => $metadata
            ]);

            Log::info('[MetaActionService] Ação executada com sucesso', [
                'action_uuid' => $action->uuid,
                'organization_id' => $action->organization_id,
                'user_id' => $action->user_id,
            ]);

            return $response;
            
        } catch (\Exception $e) {
            // Falhas de API, Rate Limit, etc.
            $action->update([
                'status' => 'failed',
                'executed_at' => now(),
                'error_data' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'class' => get_class($e),
                ]
            ]);
            
            Log::error('[MetaActionService] Falha na execução da ação', [
                'action_uuid' => $action->uuid,
                'organization_id' => $action->organization_id,
                'user_id' => $action->user_id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}

