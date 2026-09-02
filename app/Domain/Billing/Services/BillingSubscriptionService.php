<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;

/**
 * Resolve configuração efetiva da assinatura de uma organização.
 *
 * Centraliza overrides Enterprise e fallback quando não há plano.
 */
class BillingSubscriptionService
{
    /**
     * Retorna a assinatura ativa de uma organização.
     *
     * Organizações sem assinatura retornam null — sem bloquear AI.
     */
    public function activeSubscription(Organization $organization): ?OrganizationSubscription
    {
        return OrganizationSubscription::where('organization_id', $organization->id)
            ->whereIn('status', ['trial', 'active'])
            ->with('plan')
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Retorna ou cria o período de uso atual da organização.
     *
     * Se não houver assinatura ativa, cria um período sem franquia (included_credits=0)
     * para fins de registro/monitoramento.
     */
    public function currentPeriod(Organization $organization): AiUsagePeriod
    {
        $subscription = $this->activeSubscription($organization);
        $now = Carbon::now();

        // Calcula período mensal com base na assinatura ou mês corrente
        if ($subscription && $subscription->current_period_start) {
            $periodStart = Carbon::instance($subscription->current_period_start);
            $periodEnd   = Carbon::instance($subscription->current_period_end);
        } else {
            $periodStart = $now->copy()->startOfMonth();
            $periodEnd   = $now->copy()->endOfMonth();
        }

        $period = AiUsagePeriod::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
            ],
            [
                'subscription_id'  => $subscription?->id,
                'included_credits' => $subscription?->effectiveIncludedCredits() ?? 0,
                'status'           => 'open',
            ]
        );

        return $period;
    }

    /**
     * Atualiza os agregados de um período com base no novo evento.
     */
    public function updatePeriodAggregates(
        AiUsagePeriod $period,
        float $creditsUsed,
        float $providerCostBrl,
        bool  $billable
    ): void {
        if ($billable) {
            $period->increment('billable_credits_used', $creditsUsed);
            $period->increment('provider_cost_brl', $providerCostBrl);
        } else {
            $period->increment('non_billable_credits_equivalent', $creditsUsed);
            $period->increment('non_billable_provider_cost_brl', $providerCostBrl);
        }

        // Recalcular excedente
        $fresh = $period->fresh();
        $overage = max($fresh->billable_credits_used - $fresh->included_credits, 0);

        $overageCents = 0;
        if ($overage > 0) {
            $subscription = $period->subscription;
            $overagePricePer1000 = $subscription?->effectiveOveragePricePer1000Cents() ?? 0;
            $overageCents = (int) round(($overage / 1000) * $overagePricePer1000);
        }

        $fresh->update([
            'overage_credits'           => $overage,
            'estimated_overage_cents'   => $overageCents,
        ]);
    }
}
