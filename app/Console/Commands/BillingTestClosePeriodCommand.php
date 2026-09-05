<?php

namespace App\Console\Commands;

use App\Domain\Billing\Services\BillingPeriodClosingService;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Console\Command;

class BillingTestClosePeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:test-close-period {organizationId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simula o fechamento do período atual de uma organização e projeta a fatura sem alterar o banco.';

    /**
     * Execute the console command.
     */
    public function handle(
        BillingPeriodClosingService $closingService,
        BillingSubscriptionService $subscriptionService
    ): int {
        $orgId = $this->argument('organizationId');
        $organization = Organization::find($orgId);

        if (!$organization) {
            $this->error("Organização com ID {$orgId} não encontrada.");
            return 1;
        }

        $currentPeriod = $subscriptionService->currentPeriod($organization);

        $this->warn("🔍 SIMULAÇÃO DE FECHAMENTO DE PERÍODO (Somente Leitura)");
        $this->info("Organização: {$organization->name} (#{$organization->id})\n");

        $preview = $closingService->previewPeriodClosing($currentPeriod);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Plano Atual', $preview['plan_name']],
                ['Período Analisado', $preview['period_start'] . ' a ' . $preview['period_end']],
                ['Mensalidade Contratual', 'R$ ' . number_format($preview['monthly_price_cents'] / 100, 2, ',', '.')],
                ['Créditos Incluídos (Franquia)', number_format($preview['included_credits'], 0, ',', '.')],
                ['Créditos Utilizados no Período', number_format($preview['billable_credits_used'], 2, ',', '.')],
                ['Créditos Excedentes Calculados', number_format($preview['overage_credits'], 2, ',', '.')],
                ['Valor Bruto do Excedente', 'R$ ' . number_format($preview['raw_calculated_overage_cents'] / 100, 2, ',', '.')],
                ['Pós-pago Habilitado?', $preview['postpaid_enabled'] ? 'Sim' : 'Não'],
                ['Teto Pós-pago Contratual', $preview['postpaid_limit_cents'] ? 'R$ ' . number_format($preview['postpaid_limit_cents'] / 100, 2, ',', '.') : 'Sem teto'],
                ['Teto Aplicado na Simulação?', $preview['postpaid_limit_applied'] ? 'Sim (limitado pelo teto)' : 'Não'],
                ['Valor Cobrável de Excedente', 'R$ ' . number_format($preview['billed_overage_cents'] / 100, 2, ',', '.')],
                ['TOTAL PREVISTO DA FATURA', 'R$ ' . number_format($preview['total_cents'] / 100, 2, ',', '.')],
                ['Próximo Período Projetado', $preview['next_period_start'] . ' a ' . $preview['next_period_end']],
            ]
        );

        $this->info("\nℹ️ Nota: Nenhuma fatura foi criada e o status do período '{$currentPeriod->status}' permaneceu inalterado.");
        return 0;
    }
}
