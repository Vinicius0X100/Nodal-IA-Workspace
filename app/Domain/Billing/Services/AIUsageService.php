<?php

namespace App\Domain\Billing\Services;

use App\Domain\AI\Models\Conversation;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Models\AiUsageCostComponent;
use App\Domain\Billing\Models\AiUsageEvent;
use App\Domain\Billing\Models\AiUsageDailyRollup;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serviço central para registro de eventos de uso de IA.
 *
 * Responsabilidades:
 * - Garantir idempotência via idempotency_key (única no ledger)
 * - Calcular custo e créditos
 * - Persistir evento + componentes
 * - Atualizar agregados de período
 * - Atualizar rollups diários
 * - Verificar e disparar alertas de threshold
 */
class AIUsageService
{
    public function __construct(
        private readonly AICostCalculator         $calculator,
        private readonly BillingSubscriptionService $subscriptionService,
        private readonly AIUsageLimitService      $limitService,
    ) {}

    /**
     * Registra um evento de uso de IA.
     *
     * Idempotente: retorna o evento existente se idempotency_key já foi registrada.
     */
    public function record(
        Organization   $organization,
        UsageEventInput $input,
        ?User          $user         = null,
        ?Conversation  $conversation = null,
    ): AiUsageEvent {
        // 1. Idempotência: retorna existente sem recalcular
        $existing = AiUsageEvent::where('idempotency_key', $input->idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $occurredAt = $input->occurredAt ?? new \DateTime();

        // 2. Calcular custo
        $cost = $this->calculator->calculate($input, $occurredAt);

        // 3. Determinar se deve ser faturável
        // BillingCategory pode sobrescrever: ex: internal_retry = not billable
        $billable = $input->billable && $input->billingCategory->isBillableByDefault();
        $creditsUsed = $billable ? $cost->creditsUsed : 0.0;

        // 4. Persistir no ledger (transação)
        $event = DB::transaction(function () use (
            $organization, $user, $conversation, $input,
            $cost, $billable, $creditsUsed, $occurredAt
        ) {
            $event = AiUsageEvent::create([
                'uuid'                          => (string) Str::uuid(),
                'organization_id'               => $organization->id,
                'user_id'                       => $user?->id,
                'conversation_id'               => $conversation?->id,
                'provider'                      => $input->provider,
                'model'                         => $input->model,
                'operation'                     => $input->operation,
                'source'                        => $input->source,
                'request_uuid'                  => $input->requestUuid,
                'n8n_execution_id'              => $input->n8nExecutionId,
                'prompt_tokens'                 => $input->promptTokens,
                'cached_input_tokens'           => $input->cachedInputTokens,
                'output_tokens'                 => $input->outputTokens,
                'thinking_tokens'               => $input->thinkingTokens,
                'tool_use_prompt_tokens'        => $input->toolUsePromptTokens,
                'total_tokens'                  => $input->totalTokens,
                'provider_cost_usd'             => $cost->totalCostUsd,
                'exchange_rate'                 => $cost->exchangeRate,
                'provider_cost_brl'             => $cost->providerCostBrl,
                'commercial_reference_cost_brl' => $cost->commercialReferenceCostBrl,
                'credits_used'                  => $creditsUsed,
                'billable'                      => $billable,
                'billing_category'              => $input->billingCategory->value,
                'model_rate_id'                 => $cost->modelRateId,
                'exchange_rate_id'              => $cost->exchangeRateId,
                'idempotency_key'               => $input->idempotencyKey,
                'provider_usage_json'           => $input->providerUsageJson,
                'metadata_json'                 => $input->metadataJson,
                'occurred_at'                   => $occurredAt,
            ]);

            // 5. Persistir componentes de custo detalhados
            foreach ($cost->components as $component) {
                AiUsageCostComponent::create(array_merge(
                    $component,
                    ['ai_usage_event_id' => $event->id]
                ));
            }

            return $event;
        });

        // 6. Atualizar período de uso (fora da transação principal para não bloquear)
        $occurredCarbon = \Carbon\Carbon::instance($occurredAt);
        $period = $this->subscriptionService->currentPeriod($organization, $occurredCarbon);
        $this->subscriptionService->updatePeriodAggregates(
            $period,
            $cost->creditsUsed, // registra custo real independente de billable
            $cost->providerCostBrl,
            $billable
        );

        // 7. Atualizar rollup diário
        $this->updateDailyRollup($organization, $user, $input, $cost, $billable, $occurredAt);

        // 8. Verificar thresholds e disparar alertas
        $this->checkAndFireThresholds($organization, $period->fresh());

        return $event;
    }

    private function updateDailyRollup(
        Organization     $organization,
        ?User            $user,
        UsageEventInput  $input,
        $cost,
        bool             $billable,
        \DateTime        $occurredAt
    ): void {
        $date = (new \Carbon\Carbon($occurredAt))->toDateString();

        AiUsageDailyRollup::updateOrCreate(
            [
                'organization_id'  => $organization->id,
                'user_id'          => $user?->id,
                'date'             => $date,
                'provider'         => $input->provider,
                'model'            => $input->model,
                'operation'        => $input->operation,
                'billing_category' => $input->billingCategory->value,
            ],
            []
        );

        // Usar increment para evitar race conditions
        AiUsageDailyRollup::where([
            'organization_id'  => $organization->id,
            'user_id'          => $user?->id,
            'date'             => $date,
            'provider'         => $input->provider,
            'model'            => $input->model,
            'operation'        => $input->operation,
            'billing_category' => $input->billingCategory->value,
        ])->increment('credits_used', $cost->creditsUsed);

        AiUsageDailyRollup::where([
            'organization_id'  => $organization->id,
            'user_id'          => $user?->id,
            'date'             => $date,
            'provider'         => $input->provider,
            'model'            => $input->model,
            'operation'        => $input->operation,
            'billing_category' => $input->billingCategory->value,
        ])->update([
            'provider_cost_brl'      => DB::raw("provider_cost_brl + {$cost->providerCostBrl}"),
            'billable_cost_brl'      => DB::raw("billable_cost_brl + " . ($billable ? $cost->providerCostBrl : 0)),
            'prompt_tokens'          => DB::raw("prompt_tokens + {$input->promptTokens}"),
            'cached_input_tokens'    => DB::raw("cached_input_tokens + {$input->cachedInputTokens}"),
            'output_tokens'          => DB::raw("output_tokens + {$input->outputTokens}"),
            'thinking_tokens'        => DB::raw("thinking_tokens + {$input->thinkingTokens}"),
            'total_tokens'           => DB::raw("total_tokens + {$input->totalTokens}"),
            'requests_count'         => DB::raw("requests_count + 1"),
            'billable_requests_count' => DB::raw("billable_requests_count + " . ($billable ? 1 : 0)),
        ]);
    }

    private function checkAndFireThresholds(Organization $organization, $period): void
    {
        $state = $this->limitService->getUsageState($organization);
        
        // 1. Verificações de Franquia (Créditos Incluídos)
        if ($state['included_credits'] > 0) {
            $percentage = $state['usage_percentage'];

            foreach (\App\Domain\Billing\Enums\AlertType::creditUsageThresholds() as $threshold) {
                if ($percentage >= $threshold) {
                    $alertType = \App\Domain\Billing\Enums\AlertType::fromCreditThreshold($threshold);
                    $this->fireAlertEvent(
                        $organization, $period, $alertType, $threshold, $percentage
                    );
                }
            }
        }

        // 2. Verificações de Pós-Pago (se habilitado e com limite em R$)
        if ($state['postpaid_enabled'] && $state['is_over_quota']) {
            // POSTPAID_STARTED é disparado assim que o primeiro centavo de overage é gerado.
            if ($state['estimated_postpaid_used_brl'] > 0) {
                $this->fireAlertEvent($organization, $period, \App\Domain\Billing\Enums\AlertType::POSTPAID_STARTED, 0);
            }

            $limitBrl = $state['postpaid_limit_brl'];
            if ($limitBrl > 0) {
                $postpaidPercentage = ($state['estimated_postpaid_used_brl'] / $limitBrl) * 100;

                foreach (\App\Domain\Billing\Enums\AlertType::postpaidThresholds() as $threshold) {
                    if ($postpaidPercentage >= $threshold) {
                        $alertType = \App\Domain\Billing\Enums\AlertType::fromPostpaidThreshold($threshold);
                        $this->fireAlertEvent($organization, $period, $alertType, $threshold, $postpaidPercentage);
                    }
                }
            }
        }
    }

    private function fireAlertEvent(
        Organization $organization, 
        $period, 
        \App\Domain\Billing\Enums\AlertType $alertType, 
        int $threshold,
        float $percentage = 0.0
    ): void {
        $idempotencyKey = "org_{$organization->id}_period_{$period->id}_{$alertType->value}";

        $alreadyFired = \App\Domain\Billing\Models\BillingAlertEvent::where(
            'idempotency_key', $idempotencyKey
        )->exists();

        if (!$alreadyFired) {
            event(new \App\Domain\Billing\Events\AIUsageThresholdReached(
                organization: $organization,
                period: clone $period,
                alertType: $alertType,
                threshold: $threshold,
                percentage: $percentage,
                idempotencyKey: $idempotencyKey,
            ));
        }
    }
}
