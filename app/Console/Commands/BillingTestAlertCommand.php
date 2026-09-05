<?php

namespace App\Console\Commands;

use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BillingTestAlertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:test-alert {organizationId} {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispara um alerta de consumo simulado para validação (diagnóstico).';

    /**
     * Execute the console command.
     */
    public function handle(BillingSubscriptionService $subscriptionService)
    {
        $organizationId = $this->argument('organizationId');
        $typeArg = $this->argument('type');

        $organization = Organization::find($organizationId);

        if (!$organization) {
            $this->error("Organização com ID {$organizationId} não encontrada.");
            return 1;
        }

        $typeMap = [
            'credit_70'        => [AlertType::CREDIT_USAGE_70, 70],
            'credit_85'        => [AlertType::CREDIT_USAGE_85, 85],
            'credit_95'        => [AlertType::CREDIT_USAGE_95, 95],
            'credit_100'       => [AlertType::CREDIT_USAGE_100, 100],
            'postpaid_started' => [AlertType::POSTPAID_STARTED, 0],
            'postpaid_75'      => [AlertType::POSTPAID_75, 75],
            'postpaid_90'      => [AlertType::POSTPAID_90, 90],
            'postpaid_limit'   => [AlertType::POSTPAID_LIMIT_REACHED, 100],
        ];

        if (!array_key_exists($typeArg, $typeMap)) {
            $this->error("Tipo de alerta '{$typeArg}' não é suportado.");
            $this->line("Tipos permitidos: " . implode(', ', array_keys($typeMap)));
            return 1;
        }

        [$alertType, $threshold] = $typeMap[$typeArg];

        // Validar AiUsagePeriod real persistido
        $realPeriod = $subscriptionService->currentPeriod($organization);
        if (!$realPeriod) {
            $this->error("Para testes de fila funcionarem, a organização precisa ter um AiUsagePeriod persistido no banco.");
            return 1;
        }

        $isPostpaidAlert = in_array($typeArg, ['postpaid_started', 'postpaid_75', 'postpaid_90', 'postpaid_limit']);

        if ($isPostpaidAlert) {
            $subscription = $subscriptionService->activeSubscription($organization);
            if (!$subscription) {
                $this->error("Esta organização não possui uma assinatura ativa para testar alertas pós-pagos.");
                return 1;
            }

            $postpaidLimitCents = $subscription->postpaid_limit_cents;
            if (empty($postpaidLimitCents) || $postpaidLimitCents <= 0) {
                $this->error("Esta organização não possui limite pós-pago configurado. Configure o limite antes de testar alertas pós-pagos.");
                return 1;
            }

            $overagePrice = $subscription->effectiveOveragePricePer1000Cents();
            if (empty($overagePrice) || $overagePrice <= 0) {
                $this->error("Esta organização não possui preço de excedente configurado no plano ou na assinatura.");
                return 1;
            }

            $included = (float) $realPeriod->included_credits;

            if ($typeArg === 'postpaid_started') {
                $simulatedOverage = 1.0;
                $simulatedOverageCents = (int) ceil(($simulatedOverage / 1000) * $overagePrice);
                $simulatedPercentage = 0.0;
            } else {
                $targetOverageCents = (int) round($postpaidLimitCents * ($threshold / 100));
                $simulatedOverage = ($targetOverageCents / $overagePrice) * 1000;
                $simulatedOverageCents = $targetOverageCents;
                $simulatedPercentage = (float) $threshold;
            }

            $simulatedUsed = $included + $simulatedOverage;

            $simulationContext = [
                'included_credits'        => $included,
                'billable_credits_used'   => $simulatedUsed,
                'overage_credits'         => $simulatedOverage,
                'estimated_overage_cents' => $simulatedOverageCents,
                'postpaid_limit_cents'    => $postpaidLimitCents,
                'postpaid_percentage'     => $simulatedPercentage,
            ];
        } else {
            // Alertas de consumo de franquia (credit_70, credit_85, credit_95, credit_100)
            $included = (float) $realPeriod->included_credits;

            if ($included <= 0) {
                $subscription = $subscriptionService->activeSubscription($organization);
                if ($subscription && $subscription->plan && $subscription->plan->included_ai_credits > 0) {
                    $included = (float) $subscription->plan->included_ai_credits;
                } else {
                    $this->error("Esta organização não possui franquia de créditos incluídos para simular alertas de franquia.");
                    return 1;
                }
            }

            $simulatedUsed = ($threshold / 100) * $included;

            $simulationContext = [
                'included_credits'        => $included,
                'billable_credits_used'   => $simulatedUsed,
                'overage_credits'         => 0.0,
                'estimated_overage_cents' => 0,
                'postpaid_limit_cents'    => null,
                'postpaid_percentage'     => null,
            ];
        }

        $idempotencyKey = "test:org_{$organization->id}:{$alertType->value}:" . time();

        $this->info("Disparando AIUsageThresholdReached...");
        $this->info("Organization: {$organization->name} ({$organization->id})");
        $this->info("Alert Type: {$alertType->value}");
        $this->info("Threshold: {$threshold}");
        $this->info("Idempotency Key: {$idempotencyKey}");

        event(new AIUsageThresholdReached(
            organization: $organization,
            period: $realPeriod,
            alertType: $alertType,
            threshold: $threshold,
            percentage: $threshold, // Opcional/Visual
            idempotencyKey: $idempotencyKey,
            isTest: true,
            simulationContext: $simulationContext
        ));

        $this->info("\n✅ Evento disparado com sucesso!");
        $this->line("\n=== Como Validar ===");
        $this->line("1. Verifique se o BillingAlertEvent foi criado com a chave: {$idempotencyKey}");
        $this->line("   (A propriedade metadata_json conterá 'is_test' => true se o evento foi customizado)");
        $this->line("2. Verifique a tabela 'notifications' para conferir a notificação in-app criada.");
        $this->line("3. Verifique seus logs de e-mail (Mailpit/Logs) ou caixa de entrada para checar o e-mail formatado.");

        return 0;
    }
}
