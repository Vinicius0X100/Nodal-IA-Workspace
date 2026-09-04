<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Organizations\Models\Organization;

/**
 * Serviço centralizador de regras de limite de consumo de IA.
 *
 * Toda lógica de "pode consumir?" deve passar por aqui.
 * Nunca duplicar essas regras em controllers ou middleware.
 */
class AIUsageLimitService
{
    public function __construct(
        private readonly BillingSubscriptionService $subscriptionService,
    ) {}

    /**
     * Estado completo do uso de uma organização no período atual.
     *
     * Retorna array com:
     * - has_plan: bool
     * - included_credits: int
     * - credits_used: float
     * - credits_remaining: float
     * - usage_percentage: float
     * - is_over_quota: bool
     * - overage_credits: float
     * - estimated_overage_brl: float
     * - postpaid_enabled: bool
     * - postpaid_limit_brl: float|null
     * - estimated_postpaid_used_brl: float
     * - postpaid_remaining_brl: float|null
     * - can_consume: bool
     */
    public function getUsageState(Organization $organization): array
    {
        $subscription = $this->subscriptionService->activeSubscription($organization);
        $period       = $this->subscriptionService->currentPeriod($organization);

        $includedCredits     = $period->included_credits;
        $creditsUsed         = $period->billable_credits_used;
        $creditsRemaining    = max($includedCredits - $creditsUsed, 0);
        $usagePercentage     = $period->usagePercentage();
        $isOverQuota         = $period->isOverQuota();
        $overageCredits      = $period->overage_credits;
        $estimatedOverageCents = $period->estimated_overage_cents;

        $postpaidEnabled    = $subscription?->postpaid_enabled ?? false;
        $postpaidLimitCents = $subscription?->postpaid_limit_cents;

        $estimatedPostpaidUsedBrl = $estimatedOverageCents / 100;
        $postpaidRemainingBrl     = null;

        if ($postpaidEnabled && $postpaidLimitCents !== null) {
            $postpaidRemainingBrl = max(($postpaidLimitCents / 100) - $estimatedPostpaidUsedBrl, 0);
        }

        $canConsume = $this->canConsume($subscription, $period, $estimatedPostpaidUsedBrl, $postpaidLimitCents);

        return [
            'has_plan'                  => $subscription !== null,
            'included_credits'          => $includedCredits,
            'credits_used'              => $creditsUsed,
            'credits_remaining'         => $creditsRemaining,
            'usage_percentage'          => $usagePercentage,
            'is_over_quota'             => $isOverQuota,
            'overage_credits'           => $overageCredits,
            'estimated_overage_cents'   => $estimatedOverageCents,
            'estimated_overage_brl'     => $estimatedOverageCents / 100,
            'postpaid_enabled'          => $postpaidEnabled,
            'postpaid_limit_brl'        => $postpaidLimitCents !== null ? $postpaidLimitCents / 100 : null,
            'estimated_postpaid_used_brl' => $estimatedPostpaidUsedBrl,
            'postpaid_remaining_brl'    => $postpaidRemainingBrl,
            'can_consume'               => $canConsume,
            'period_start'              => $period->period_start?->toISOString(),
            'period_end'                => $period->period_end?->toISOString(),
        ];
    }

    /**
     * Verifica se a organização pode consumir mais créditos de IA.
     */
    public function canConsume(
        ?OrganizationSubscription $subscription,
        AiUsagePeriod             $period,
        float                     $currentPostpaidUsedBrl,
        ?int                      $postpaidLimitCents
    ): bool {
        // Sem plano: permite consumo (modo de monitoramento)
        if (!$subscription) {
            return true;
        }

        // Dentro da franquia: sempre pode
        if (!$period->isOverQuota()) {
            return true;
        }

        // Além da franquia: verificar pós-pago
        $postpaidEnabled = $subscription->postpaid_enabled;

        if (!$postpaidEnabled) {
            return false; // Pós-pago não habilitado — bloquear
        }

        // Pós-pago habilitado sem limite: pode consumir
        if ($postpaidLimitCents === null) {
            return true;
        }

        // Verificar se ainda há limite disponível
        return $currentPostpaidUsedBrl < ($postpaidLimitCents / 100);
    }

    public function getRemainingIncludedCredits(Organization $organization): float
    {
        $period = $this->subscriptionService->currentPeriod($organization);
        return max($period->included_credits - $period->billable_credits_used, 0);
    }

    public function getOverageCredits(Organization $organization): float
    {
        $period = $this->subscriptionService->currentPeriod($organization);
        return $period->overage_credits;
    }

    public function getEstimatedOverage(Organization $organization): int
    {
        $period = $this->subscriptionService->currentPeriod($organization);
        return $period->estimated_overage_cents;
    }

    public function getRemainingPostpaidAmount(Organization $organization): ?float
    {
        $state = $this->getUsageState($organization);
        return $state['postpaid_remaining_brl'];
    }

    public function isPostpaidLimitReached(Organization $organization): bool
    {
        $state = $this->getUsageState($organization);
        
        if (!$state['postpaid_enabled']) {
            return false;
        }

        if ($state['postpaid_limit_brl'] === null) {
            return false; // Ilimitado
        }

        return $state['postpaid_remaining_brl'] <= 0;
    }
}
