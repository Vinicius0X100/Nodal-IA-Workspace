<?php

namespace App\Domain\Billing\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingInvoiceItem;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serviço de domínio autoritativo para Fechamento de Período de Faturamento e Emissão de Faturas.
 *
 * Responsabilidades:
 * - Localizar e processar períodos vencidos (period_end < now()).
 * - Executar fechamento transacional e seguro contra concorrência (lockForUpdate).
 * - Congelar os valores financeiros no período (status 'invoiced').
 * - Criar a BillingInvoice e os BillingInvoiceItems com snapshot histórico.
 * - Abrir o próximo AiUsagePeriod e avançar as datas da assinatura (sem rollover).
 * - Auditoria completa de fechamento e emissão via AuditLog.
 */
class BillingPeriodClosingService
{
    public function __construct(
        private readonly BillingSubscriptionService $subscriptionService,
    ) {}

    /**
     * Localiza todos os períodos abertos e vencidos e executa o fechamento.
     */
    public function closeDuePeriods(bool $dryRun = false, ?int $organizationId = null): array
    {
        $query = AiUsagePeriod::where('status', 'open')
            ->where('period_end', '<', Carbon::now())
            ->with(['organization', 'subscription.plan']);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $periods = $query->orderBy('period_end')->get();

        $summary = [
            'found'               => $periods->count(),
            'closed'              => 0,
            'invoices_created'    => 0,
            'next_periods_opened' => 0,
            'errors'              => 0,
            'details'             => [],
        ];

        foreach ($periods as $period) {
            if ($dryRun) {
                $preview = $this->previewPeriodClosing($period);
                $summary['details'][] = $preview;
                $summary['closed']++;
                $summary['invoices_created']++;
                $summary['next_periods_opened']++;
                continue;
            }

            try {
                $invoice = $this->closePeriod($period);
                if ($invoice) {
                    $summary['closed']++;
                    $summary['invoices_created']++;
                    $summary['next_periods_opened']++;
                    $summary['details'][] = [
                        'period_id'     => $period->id,
                        'invoice_id'    => $invoice->id,
                        'invoice_uuid'  => $invoice->uuid,
                        'organization'  => $period->organization->name,
                        'total_cents'   => $invoice->total_cents,
                        'status'        => 'success',
                    ];
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['details'][] = [
                    'period_id'    => $period->id,
                    'organization' => $period->organization->name,
                    'status'       => 'error',
                    'error'        => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Executa o fechamento transacional de um AiUsagePeriod específico.
     */
    public function closePeriod(AiUsagePeriod $period): ?BillingInvoice
    {
        return DB::transaction(function () use ($period) {
            // 1. Lock do período para concorrência
            $lockedPeriod = AiUsagePeriod::where('id', $period->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$lockedPeriod) {
                // Período já fechado ou não aberto
                return BillingInvoice::where('usage_period_id', $period->id)->first();
            }

            // 2. Idempotência forte: se já tiver invoice associada, não duplicar
            $existingInvoice = BillingInvoice::where('usage_period_id', $lockedPeriod->id)->first();
            if ($existingInvoice) {
                if ($lockedPeriod->status === 'open') {
                    $lockedPeriod->update(['status' => 'invoiced']);
                }
                return $existingInvoice;
            }

            $organization = $lockedPeriod->organization;
            $subscription = $lockedPeriod->subscription ?? $this->subscriptionService->activeSubscription($organization);

            // 3. Snapshot do plano
            $plan = $subscription?->plan;
            $planName = $plan?->name ?? 'Plano Padrão';
            $planCode = $plan?->code ?? 'default';

            $monthlyPriceCents = $subscription?->effectiveMonthlyPriceCents() ?? 0;
            $overagePricePer1000 = $subscription?->effectiveOveragePricePer1000Cents() ?? 0;

            // 4. Cálculo do excedente
            $overageCredits = max((float) $lockedPeriod->billable_credits_used - (float) $lockedPeriod->included_credits, 0.0);

            $rawCalculatedOverageCents = 0;
            if ($overageCredits > 0 && $overagePricePer1000 > 0) {
                $rawCalculatedOverageCents = (int) round(($overageCredits / 1000) * $overagePricePer1000);
            }

            // 5. Regras de Pós-pago e Limite Contratual
            $postpaidEnabled = (bool) ($subscription?->postpaid_enabled ?? false);
            $postpaidLimitCents = $subscription?->postpaid_limit_cents;
            $billedOverageCents = 0;
            $postpaidLimitApplied = false;

            if ($postpaidEnabled && $rawCalculatedOverageCents > 0) {
                $billedOverageCents = $rawCalculatedOverageCents;
                if ($postpaidLimitCents !== null && $postpaidLimitCents > 0) {
                    if ($billedOverageCents > $postpaidLimitCents) {
                        $billedOverageCents = $postpaidLimitCents;
                        $postpaidLimitApplied = true;
                    }
                }
            }

            $subtotalCents = $monthlyPriceCents + $billedOverageCents;
            $adjustmentsCents = 0;
            $totalCents = $subtotalCents + $adjustmentsCents;

            // 6. Transição de status do período: open -> invoiced
            $lockedPeriod->update([
                'status'                  => 'invoiced',
                'overage_credits'         => $overageCredits,
                'estimated_overage_cents' => $rawCalculatedOverageCents,
            ]);

            // 7. Criar BillingInvoice com snapshot histórico
            $invoice = BillingInvoice::create([
                'uuid'              => (string) Str::uuid(),
                'organization_id'   => $lockedPeriod->organization_id,
                'subscription_id'   => $subscription?->id,
                'usage_period_id'   => $lockedPeriod->id,
                'plan_name'         => $planName,
                'plan_code'         => $planCode,
                'period_start'      => $lockedPeriod->period_start,
                'period_end'        => $lockedPeriod->period_end,
                'status'            => 'draft',
                'subtotal_cents'    => $subtotalCents,
                'overage_cents'     => $billedOverageCents,
                'adjustments_cents' => $adjustmentsCents,
                'total_cents'       => $totalCents,
                'issued_at'         => null,
                'due_at'            => null,
                'paid_at'           => null,
                'metadata_json'     => [
                    'plan_id'                              => $plan?->id,
                    'plan_code'                            => $planCode,
                    'plan_name'                            => $planName,
                    'monthly_price_cents'                  => $monthlyPriceCents,
                    'included_credits'                     => (float) $lockedPeriod->included_credits,
                    'billable_credits_used'                => (float) $lockedPeriod->billable_credits_used,
                    'overage_credits'                      => $overageCredits,
                    'overage_price_per_1000_credits_cents' => $overagePricePer1000,
                    'postpaid_enabled'                     => $postpaidEnabled,
                    'postpaid_limit_cents'                 => $postpaidLimitCents,
                    'postpaid_limit_applied'               => $postpaidLimitApplied,
                    'raw_calculated_overage_cents'         => $rawCalculatedOverageCents,
                    'billed_overage_cents'                 => $billedOverageCents,
                    'custom_overrides'                     => [
                        'custom_monthly_price_cents'                  => $subscription?->custom_monthly_price_cents,
                        'custom_included_ai_credits'                  => $subscription?->custom_included_ai_credits,
                        'custom_overage_price_per_1000_credits_cents' => $subscription?->custom_overage_price_per_1000_credits_cents,
                    ],
                    'closed_at'                            => Carbon::now()->toIsoString(),
                ],
            ]);

            // 8. Criar BillingInvoiceItems
            $periodLabel = ucfirst($lockedPeriod->period_start->translatedFormat('F/Y'));

            // Item 1: Mensalidade
            BillingInvoiceItem::create([
                'invoice_id'        => $invoice->id,
                'type'              => 'subscription',
                'description'       => "Plano {$planName} — {$periodLabel}",
                'quantity'          => 1,
                'unit_amount_cents' => $monthlyPriceCents,
                'amount_cents'      => $monthlyPriceCents,
                'metadata_json'     => [
                    'plan_id'   => $plan?->id,
                    'plan_code' => $planCode,
                    'plan_name' => $planName,
                ],
            ]);

            // Item 2: Uso Adicional de IA (somente se houver valor faturável autorizado)
            if ($billedOverageCents > 0) {
                BillingInvoiceItem::create([
                    'invoice_id'        => $invoice->id,
                    'type'              => 'ai_overage',
                    'description'       => "Uso adicional de IA — {$periodLabel}",
                    'quantity'          => $overageCredits,
                    'unit_amount_cents' => $overagePricePer1000,
                    'amount_cents'      => $billedOverageCents,
                    'metadata_json'     => [
                        'overage_credits'              => $overageCredits,
                        'price_per_1000_credits_cents' => $overagePricePer1000,
                        'usage_period_id'              => $lockedPeriod->id,
                        'postpaid_limit_cents'         => $postpaidLimitCents,
                        'raw_calculated_overage_cents' => $rawCalculatedOverageCents,
                        'billed_overage_cents'         => $billedOverageCents,
                        'postpaid_limit_applied'       => $postpaidLimitApplied,
                    ],
                ]);
            }

            // 9. Abrir próximo AiUsagePeriod sem sobreposição e sem acumular créditos
            $nextPeriodStart = $lockedPeriod->period_end->copy()->addSecond()->startOfDay();
            $nextPeriodEnd   = $nextPeriodStart->copy()->endOfMonth();

            AiUsagePeriod::firstOrCreate(
                [
                    'organization_id' => $lockedPeriod->organization_id,
                    'period_start'    => $nextPeriodStart,
                    'period_end'      => $nextPeriodEnd,
                ],
                [
                    'subscription_id'                 => $subscription?->id,
                    'included_credits'                => $subscription?->effectiveIncludedCredits() ?? 0,
                    'billable_credits_used'           => 0,
                    'non_billable_credits_equivalent' => 0,
                    'provider_cost_brl'               => 0,
                    'non_billable_provider_cost_brl'  => 0,
                    'overage_credits'                 => 0,
                    'estimated_overage_cents'         => 0,
                    'status'                          => 'open',
                ]
            );

            // 10. Atualizar datas da Subscription silenciosamente para evitar recursão
            if ($subscription) {
                $subscription->updateQuietly([
                    'current_period_start' => $nextPeriodStart,
                    'current_period_end'   => $nextPeriodEnd,
                ]);
            }

            // 11. Auditoria
            AuditLog::create([
                'organization_id' => $lockedPeriod->organization_id,
                'user_id'         => auth()->id(),
                'action'          => 'billing_period_closed',
                'entity_type'     => AiUsagePeriod::class,
                'entity_id'       => (string) $lockedPeriod->id,
                'metadata'        => [
                    'period_start' => $lockedPeriod->period_start?->toIsoString(),
                    'period_end'   => $lockedPeriod->period_end?->toIsoString(),
                    'invoice_id'   => $invoice->id,
                    'status'       => 'invoiced',
                ],
            ]);

            AuditLog::create([
                'organization_id' => $lockedPeriod->organization_id,
                'user_id'         => auth()->id(),
                'action'          => 'billing_invoice_created',
                'entity_type'     => BillingInvoice::class,
                'entity_id'       => (string) $invoice->id,
                'metadata'        => [
                    'subtotal_cents' => $invoice->subtotal_cents,
                    'overage_cents'  => $invoice->overage_cents,
                    'total_cents'    => $invoice->total_cents,
                    'status'         => $invoice->status->value,
                ],
            ]);

            return $invoice;
        });
    }

    /**
     * Calcula e projeta o fechamento de um período sem persistir nada no banco (dry-run).
     */
    public function previewPeriodClosing(AiUsagePeriod $period): array
    {
        $organization = $period->organization;
        $subscription = $period->subscription ?? $this->subscriptionService->activeSubscription($organization);

        $plan = $subscription?->plan;
        $planName = $plan?->name ?? 'Plano Padrão';
        $planCode = $plan?->code ?? 'default';

        $monthlyPriceCents = $subscription?->effectiveMonthlyPriceCents() ?? 0;
        $overagePricePer1000 = $subscription?->effectiveOveragePricePer1000Cents() ?? 0;

        $overageCredits = max((float) $period->billable_credits_used - (float) $period->included_credits, 0.0);

        $rawCalculatedOverageCents = 0;
        if ($overageCredits > 0 && $overagePricePer1000 > 0) {
            $rawCalculatedOverageCents = (int) round(($overageCredits / 1000) * $overagePricePer1000);
        }

        $postpaidEnabled = (bool) ($subscription?->postpaid_enabled ?? false);
        $postpaidLimitCents = $subscription?->postpaid_limit_cents;
        $billedOverageCents = 0;
        $postpaidLimitApplied = false;

        if ($postpaidEnabled && $rawCalculatedOverageCents > 0) {
            $billedOverageCents = $rawCalculatedOverageCents;
            if ($postpaidLimitCents !== null && $postpaidLimitCents > 0) {
                if ($billedOverageCents > $postpaidLimitCents) {
                    $billedOverageCents = $postpaidLimitCents;
                    $postpaidLimitApplied = true;
                }
            }
        }

        $subtotalCents = $monthlyPriceCents + $billedOverageCents;
        $totalCents = $subtotalCents;

        $nextPeriodStart = $period->period_end->copy()->addSecond()->startOfDay();
        $nextPeriodEnd   = $nextPeriodStart->copy()->endOfMonth();

        return [
            'period_id'                    => $period->id,
            'organization_id'              => $organization->id,
            'organization_name'            => $organization->name,
            'period_start'                 => $period->period_start?->format('d/m/Y'),
            'period_end'                   => $period->period_end?->format('d/m/Y'),
            'plan_name'                    => $planName,
            'plan_code'                    => $planCode,
            'monthly_price_cents'          => $monthlyPriceCents,
            'included_credits'             => (float) $period->included_credits,
            'billable_credits_used'        => (float) $period->billable_credits_used,
            'overage_credits'              => $overageCredits,
            'raw_calculated_overage_cents' => $rawCalculatedOverageCents,
            'billed_overage_cents'         => $billedOverageCents,
            'postpaid_enabled'             => $postpaidEnabled,
            'postpaid_limit_cents'         => $postpaidLimitCents,
            'postpaid_limit_applied'       => $postpaidLimitApplied,
            'total_cents'                  => $totalCents,
            'next_period_start'            => $nextPeriodStart->format('d/m/Y'),
            'next_period_end'              => $nextPeriodEnd->format('d/m/Y'),
        ];
    }
}
