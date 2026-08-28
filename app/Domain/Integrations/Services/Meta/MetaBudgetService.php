<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\AI\Models\AIAction;
use App\Domain\Financial\Services\CurrencyHelper;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaBudgetService
{
    public function __construct(
        private MetaMarketingClient $client
    ) {}

    public function prepareBudgetUpdate(
        Organization $organization,
        User $user,
        Integration $integration,
        string $resourceUuid,
        float $proposedBudget,
        ?string $budgetType,
        ?string $conversationUuid
    ): array {
        // 1. Resolve o resource alvo (Campaign ou Ad Set)
        $resource = IntegrationResource::where('uuid', $resourceUuid)
            ->where('integration_id', $integration->id)
            ->first();

        if (!$resource) {
            throw new \InvalidArgumentException('Recurso inválido ou não encontrado.');
        }

        $type = $resource->resource_type instanceof \BackedEnum
            ? $resource->resource_type->value
            : (string) $resource->resource_type;

        if (!in_array($type, ['campaign', 'ad_set'])) {
            throw new \InvalidArgumentException('Recurso inválido ou não suportado para alteração de orçamento.');
        }

        // 2. Identifica CBO vs Ad Set Budget (Budget Owner)
        $budgetOwner = $this->resolveBudgetOwner($resource);

        if (!$budgetOwner) {
            throw new \InvalidArgumentException('Não foi possível determinar a origem do orçamento para este recurso.');
        }

        if ($resource->id !== $budgetOwner->id) {
            throw new \InvalidArgumentException(
                "O orçamento deste Ad Set é controlado pela Campanha ({$budgetOwner->name}). Altere o orçamento diretamente na Campanha."
            );
        }

        // 3. Obtém a Moeda da Ad Account Ancestral
        $adAccount = $this->getAdAccount($budgetOwner);
        if (!$adAccount) {
            throw new \InvalidArgumentException('Conta de anúncio não encontrada para obter a moeda (currency).');
        }
        $currency = $adAccount->metadata_json['currency'] ?? null;
        if (!$currency) {
            throw new \InvalidArgumentException('Moeda não definida na conta de anúncio.');
        }

        // 4. Analisa orçamento atual e tipo
        $currentDaily = $budgetOwner->metadata_json['daily_budget'] ?? null;
        $currentLifetime = $budgetOwner->metadata_json['lifetime_budget'] ?? null;

        $resolvedBudgetType = 'daily_budget';
        $currentBudgetSubunit = 0;

        if ($currentLifetime > 0 && empty($currentDaily)) {
            $resolvedBudgetType = 'lifetime_budget';
            $currentBudgetSubunit = (int) $currentLifetime;
        } elseif ($currentDaily > 0) {
            $resolvedBudgetType = 'daily_budget';
            $currentBudgetSubunit = (int) $currentDaily;
        } else {
            // Se o recurso aceita budget mas não tem nenhum setado (ex: Ad Set sem budget e sem CBO)
            if ($budgetType) {
                // Se o agente sugeriu um budgetType válido
                if (!in_array($budgetType, ['daily', 'lifetime'])) {
                    throw new \InvalidArgumentException("Tipo de orçamento inválido: {$budgetType}");
                }
                $resolvedBudgetType = $budgetType . '_budget';
            }
        }

        if ($budgetType && $budgetType !== str_replace('_budget', '', $resolvedBudgetType) && $currentBudgetSubunit > 0) {
            throw new \InvalidArgumentException(
                "Não é possível mudar o tipo de orçamento (de " . str_replace('_budget', '', $resolvedBudgetType) . " para {$budgetType}) via AI. Apenas o valor pode ser alterado."
            );
        }

        $currentBudgetDecimal = CurrencyHelper::toDecimal($currentBudgetSubunit, $currency);

        // 5. Validação de Guardrails e Limites
        $proposedBudgetSubunit = CurrencyHelper::toLowestDenomination($proposedBudget, $currency);

        $this->validateGuardrails($proposedBudget, $currentBudgetDecimal, $currency, $resolvedBudgetType);

        // 6. Diferença e Preparação
        $differenceDecimal = $proposedBudget - $currentBudgetDecimal;
        $differencePercent = $currentBudgetDecimal > 0 
            ? round(($differenceDecimal / $currentBudgetDecimal) * 100, 2) 
            : 100.0;

        $action = AIAction::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'conversation_id' => $conversationUuid,
            'integration_id' => $integration->id,
            'provider' => 'meta',
            'action_type' => 'budget.update',
            'target_resource_uuid' => $budgetOwner->uuid,
            'prepared_params' => [
                'budget' => $proposedBudgetSubunit,
                'budget_type' => $resolvedBudgetType,
            ],
            'snapshot' => [
                'effective_budget' => $currentBudgetDecimal,
                'budget_owner_uuid' => $budgetOwner->uuid,
                'currency' => $currency,
                'budget_type' => $resolvedBudgetType,
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'prepared_at' => now(),
        ]);

        return [
            'action_uuid' => $action->uuid,
            'status' => 'pending',
            'resource' => [
                'uuid' => $budgetOwner->uuid,
                'type' => $budgetOwner->resource_type,
                'name' => $budgetOwner->name,
            ],
            'budget' => [
                'type' => str_replace('_budget', '', $resolvedBudgetType),
                'currency' => $currency,
                'current' => $currentBudgetDecimal,
                'proposed' => $proposedBudget,
                'difference' => round($differenceDecimal, 2),
                'difference_percent' => $differencePercent,
            ],
            'expires_at' => $action->expires_at->toIso8601String(),
        ];
    }

    public function executeBudgetUpdate(string $actionUuid): array
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
            $snapshotBudget = $action->snapshot['effective_budget'] ?? 0;
            $snapshotType = $action->snapshot['budget_type'] ?? 'daily_budget';
            $snapshotCurrency = $action->snapshot['currency'] ?? 'USD';

            $currentSubunit = $resource->metadata_json[$snapshotType] ?? 0;
            $currentDecimal = CurrencyHelper::toDecimal($currentSubunit, $snapshotCurrency);

            if (abs($currentDecimal - $snapshotBudget) > 0.01) {
                $action->update([
                    'status' => 'failed',
                    'error_data' => [
                        'message' => 'O orçamento do recurso foi alterado por outra fonte desde a preparação da ação (conflito de estado).',
                        'current' => $currentDecimal,
                        'snapshot' => $snapshotBudget,
                    ]
                ]);
                return ['error' => 'Conflito de estado do recurso (orçamento foi alterado externamente).', 'type' => 'invalid_argument'];
            }

            $proposedSubunit = $action->prepared_params['budget'] ?? 0;
            $proposedDecimal = CurrencyHelper::toDecimal($proposedSubunit, $snapshotCurrency);

            if (abs($currentDecimal - $proposedDecimal) < 0.01) {
                $action->update([
                    'status' => 'failed',
                    'error_data' => ['message' => 'O recurso já se encontra com o orçamento desejado. Nenhuma ação será realizada.']
                ]);
                return ['error' => 'O recurso já se encontra com o orçamento desejado.', 'type' => 'invalid_argument'];
            }

            // Transição segura para EXECUTING
            $action->update(['status' => 'executing']);
            
            Log::info('[MetaBudgetService] Ação entrou em executing', [
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
        
        $budgetType = $action->prepared_params['budget_type'];
        $budgetSubunit = $action->prepared_params['budget'];
        $externalId = $resource->external_id;
        $endpoint = "/{$externalId}";

        try {
            $response = $this->client->post($endpoint, $integration, [
                $budgetType => $budgetSubunit
            ]);

            // Atualiza o Action (Sucesso)
            $action->update([
                'status' => 'executed',
                'executed_at' => now(),
                'result_data' => $response,
            ]);

            // Atualiza o estado local do resource para refletir a mudança imediatamente
            $metadata = $resource->metadata_json ?? [];
            $metadata[$budgetType] = $budgetSubunit;
            
            $resource->update([
                'metadata_json' => $metadata
            ]);

            Log::info('[MetaBudgetService] Orçamento atualizado com sucesso na Meta', [
                'action_uuid' => $action->uuid,
                'organization_id' => $action->organization_id,
                'user_id' => $action->user_id,
                'budget_type' => $budgetType,
                'budget_subunit' => $budgetSubunit,
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
            
            Log::error('[MetaBudgetService] Falha na atualização de orçamento', [
                'action_uuid' => $action->uuid,
                'organization_id' => $action->organization_id,
                'user_id' => $action->user_id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Identifica qual recurso controla o orçamento.
     * Retorna a Campaign se for CBO, ou o Ad Set se o orçamento for dele.
     * Retorna null se não houver budget definido nem na Campaign nem no Ad Set, 
     * ou se o tipo de recurso for inválido.
     */
    private function resolveBudgetOwner(IntegrationResource $resource): ?IntegrationResource
    {
        $type = $resource->resource_type instanceof \BackedEnum
            ? $resource->resource_type->value
            : (string) $resource->resource_type;

        if ($type === 'campaign') {
            // Verifica se a campanha possui budget
            if (!empty($resource->metadata_json['daily_budget']) || !empty($resource->metadata_json['lifetime_budget'])) {
                return $resource;
            }
            return null;
        }

        if ($type === 'ad_set') {
            // Verifica se a campanha mãe possui budget (CBO)
            $campaign = IntegrationResource::where('external_id', $resource->parent_external_id)
                ->where('integration_id', $resource->integration_id)
                ->first();

            if ($campaign && (!empty($campaign->metadata_json['daily_budget']) || !empty($campaign->metadata_json['lifetime_budget']))) {
                return $campaign;
            }

            // A campanha não possui budget, então o Ad Set é o dono do orçamento
            return $resource;
        }

        return null;
    }

    private function getAdAccount(IntegrationResource $resource): ?IntegrationResource
    {
        $type = $resource->resource_type instanceof \BackedEnum
            ? $resource->resource_type->value
            : (string) $resource->resource_type;

        if ($type === 'campaign') {
            return IntegrationResource::where('external_id', $resource->parent_external_id)
                ->where('integration_id', $resource->integration_id)
                ->first();
        }

        if ($type === 'ad_set') {
            $campaign = IntegrationResource::where('external_id', $resource->parent_external_id)
                ->where('integration_id', $resource->integration_id)
                ->first();

            if ($campaign) {
                return IntegrationResource::where('external_id', $campaign->parent_external_id)
                    ->where('integration_id', $resource->integration_id)
                    ->where('resource_type', 'ad_account')
                    ->first();
            }
        }

        return null;
    }

    private function validateGuardrails(float $proposedBudget, float $currentBudget, string $currency, string $budgetType): void
    {
        if ($proposedBudget <= 0) {
            throw new \InvalidArgumentException('O orçamento proposto deve ser maior que zero.');
        }

        $minAllowed = CurrencyHelper::getMinimumDecimalAmount($currency);
        if ($proposedBudget < $minAllowed) {
            throw new \InvalidArgumentException("O orçamento proposto não atinge o valor mínimo aceitável para a moeda ({$minAllowed} {$currency}).");
        }

        // Se o orçamento atual for 0, só validamos o limite absoluto máximo
        if ($currentBudget > 0) {
            $maxIncrease = config('ai_guardrails.financial.max_increase_percent', 50);
            $maxDecrease = config('ai_guardrails.financial.max_decrease_percent', 90);

            $increasePercent = (($proposedBudget - $currentBudget) / $currentBudget) * 100;
            if ($increasePercent > $maxIncrease) {
                throw new \InvalidArgumentException("GUARDRAIL_EXCEEDED: O aumento proposto de " . round($increasePercent, 2) . "% excede o limite máximo permitido de {$maxIncrease}%.");
            }

            $decreasePercent = (($currentBudget - $proposedBudget) / $currentBudget) * 100;
            if ($decreasePercent > $maxDecrease) {
                throw new \InvalidArgumentException("GUARDRAIL_EXCEEDED: A redução proposta de " . round($decreasePercent, 2) . "% excede o limite máximo permitido de {$maxDecrease}%.");
            }
        }

        $maxAbsolute = $budgetType === 'lifetime_budget' 
            ? config('ai_guardrails.financial.max_lifetime_budget_absolute', 50000)
            : config('ai_guardrails.financial.max_daily_budget_absolute', 5000);

        if ($proposedBudget > $maxAbsolute) {
            throw new \InvalidArgumentException("GUARDRAIL_EXCEEDED: O orçamento proposto ({$proposedBudget} {$currency}) excede o limite absoluto configurado de {$maxAbsolute}.");
        }
    }
}
