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
        // mas não alteramos nem persistimos nada do período.
        $period = $subscriptionService->currentPeriod($organization);

        if (!$period) {
            // Se a org não tiver período, criamos um dummy apenas em memória
            $period = new AiUsagePeriod([
                'id' => 99999, // dummy ID
                'organization_id' => $organization->id,
                'included_credits' => 1000,
                'billable_credits_used' => ($threshold / 100) * 1000,
                'overage_credits' => 0,
                'estimated_overage_cents' => 0,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
            ]);
            $this->warn("Aviso: Organização não possui AiUsagePeriod aberto. Usando um em memória para gerar o e-mail.");
        }

        $idempotencyKey = "test:org_{$organization->id}:{$alertType->value}:" . time();

        $this->info("Disparando AIUsageThresholdReached...");
        $this->info("Organization: {$organization->name} ({$organization->id})");
        $this->info("Alert Type: {$alertType->value}");
        $this->info("Threshold: {$threshold}");
        $this->info("Idempotency Key: {$idempotencyKey}");

        event(new AIUsageThresholdReached(
            organization: $organization,
            period: $period,
            alertType: $alertType,
            threshold: $threshold,
            percentage: $threshold, // Opcional/Visual
            idempotencyKey: $idempotencyKey,
            isTest: true
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
