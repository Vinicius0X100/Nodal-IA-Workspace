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

        // Precisamos do período atual para que o e-mail exiba as variáveis de forma verídica,
        // mas não alteramos nem persistimos nada do período real (se houver).
        $realPeriod = $subscriptionService->currentPeriod($organization);
        
        $included = $realPeriod ? $realPeriod->included_credits : 50000;
        
        $overagePrice = 0;
        $postpaidLimitCents = null;

        $subscription = $subscriptionService->activeSubscription($organization);
        if ($subscription) {
            $overagePrice = $subscription->effectiveOveragePricePer1000Cents();
            $postpaidLimitCents = $subscription->postpaid_limit_cents;
        } else {
            $overagePrice = 1500; // default $15.00
            $postpaidLimitCents = 5000; // default $50.00
        }

        $simulatedUsed = 0;
        $simulatedOverage = 0;
        $simulatedOverageCents = 0;

        if (in_array($typeArg, ['credit_70', 'credit_85', 'credit_95', 'credit_100'])) {
            $simulatedUsed = ($threshold / 100) * $included;
        } else {
            // Postpaid tests
            if ($typeArg === 'postpaid_started') {
                // Just above included
                $simulatedOverage = 1; 
                $simulatedOverageCents = ($simulatedOverage / 1000) * $overagePrice;
            } else {
                // To reach $threshold% of the $postpaidLimit
                $targetOverageCents = $postpaidLimitCents * ($threshold / 100);
                
                if ($overagePrice > 0) {
                    $simulatedOverage = ($targetOverageCents / $overagePrice) * 1000;
                } else {
                    $simulatedOverage = 1000; // fallback just in case
                }
                
                $simulatedOverageCents = $targetOverageCents;
            }
            $simulatedUsed = $included + $simulatedOverage;
            if ($simulatedOverageCents === 0 && $simulatedOverage > 0) {
                 $simulatedOverageCents = ($simulatedOverage / 1000) * $overagePrice;
            }
        }

        $simulationContext = [
            'included_credits' => $included,
            'billable_credits_used' => $simulatedUsed,
            'overage_credits' => $simulatedOverage,
            'estimated_overage_cents' => $simulatedOverageCents,
            'postpaid_limit_cents' => $postpaidLimitCents,
            'postpaid_percentage' => $threshold,
        ];
        
        if (!$realPeriod) {
            $this->warn("Aviso: Organização não possui AiUsagePeriod em aberto. O modelo passado ao evento será incompleto.");
            // Creates a temporary empty model but it won't have an ID, however we try to get one from DB if possible
            // But since the org has no period, this is a diagnostic edge-case. Let's create a minimal persisted one or just pass a non-persisted one?
            // The prompt says: "Event recebe AiUsagePeriod REAL persistido". 
            // If they don't have one, we should probably fail the command to enforce realistic testing.
            $this->error("Para testes de fila funcionarem, a organização precisa ter um AiUsagePeriod persistido no banco.");
            return 1;
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
